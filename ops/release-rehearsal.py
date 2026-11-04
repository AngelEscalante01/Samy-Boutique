#!/usr/bin/env python3
"""Restore/audit a named migration rehearsal. Never starts a web app or worker.

Run in the private release directory on the VPS with sudo already authenticated.
Only creates a fresh samy_migration_* schema; refuses existing destinations.
Reports contain schema metadata, counts, sums and digests, not business rows.
"""
import argparse
import decimal
import gzip
import hashlib
import json
import os
import pathlib
import re
import secrets
import subprocess


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('backup', type=pathlib.Path)
    parser.add_argument('--sha256', required=True)
    parser.add_argument('--database', required=True)
    args = parser.parse_args()
    if not re.fullmatch(r'samy_migration_[0-9]{8}[a-z0-9_]*', args.database):
        raise SystemExit('Dedicated migration schema required; backup/app schemas forbidden')
    os.umask(0o077)
    env = dict(line.split('=', 1) for line in pathlib.Path('.env.release.private').read_text().splitlines()
               if line and not line.startswith('#'))
    if env['SAMY_RELEASE_MODE'] != 'preview' or env['DB_DATABASE'] != 'samy_release_preview':
        raise SystemExit('Run only from the isolated release preview')
    raw = args.backup.read_bytes()
    if hashlib.sha256(raw).hexdigest().lower() != args.sha256.lower():
        raise SystemExit('Backup hash mismatch')
    sql_dump = gzip.decompress(raw)
    if re.search(rb'^\s*(USE |CREATE DATABASE|DROP DATABASE)|DEFINER\s*=', sql_dump, re.M | re.I):
        raise SystemExit('Database switching/DEFINER requires private manual review')
    docker = ['sudo', '-n', 'docker']
    compose = docker + ['compose', '--env-file', '.env.release.private', '-f', 'compose.release.yaml']
    container = subprocess.check_output(compose + ['ps', '-q', 'mysql'], text=True).strip()
    if not container:
        raise SystemExit('Dedicated release database container must be running')

    def query(sql, database=None, binary=False):
        command = docker + ['exec', '-i', '-e', 'MYSQL_PWD=' + env['RELEASE_DB_ROOT_PASSWORD'],
                            container, 'mariadb', '-uroot', '-N', '-B', '--raw']
        if database:
            command.append(database)
        result = subprocess.run(command, input=sql.encode() if isinstance(sql, str) else sql,
                                capture_output=True)
        if result.returncode:
            pathlib.Path('rehearsal-sql-error.private.log').write_bytes(result.stderr)
            raise RuntimeError('SQL failed; inspect private rehearsal log (no rows printed)')
        return result.stdout if binary else result.stdout.decode().strip()

    db = args.database
    if query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='" + db + "'"):
        raise SystemExit('Destination already exists; refusing to overwrite')
    password = secrets.token_hex(24)
    query(f"CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; "
          f"CREATE USER '{db}'@'%' IDENTIFIED BY '{password}'; GRANT ALL ON `{db}`.* TO '{db}'@'%';")
    query(sql_dump, db)
    report_dir = pathlib.Path('private-audit') / db
    report_dir.mkdir(parents=True)
    tables = query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{db}' ORDER BY TABLE_NAME").splitlines()
    columns = {}
    for table in tables:
        if not re.fullmatch('[a-zA-Z0-9_]+', table):
            raise SystemExit('Unexpected identifier')
        rows = query(f"SELECT COLUMN_NAME,DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{db}' AND TABLE_NAME='{table}' ORDER BY ORDINAL_POSITION")
        columns[table] = [row.split('\t') for row in rows.splitlines()]

    def audit():
        output = {}
        for table, cols in columns.items():
            names = ','.join('`' + c[0] + '`' for c in cols)
            # Hex-encode JSON so embedded tabs/newlines cannot alter row boundaries.
            rows = query(f'SELECT HEX(JSON_ARRAY({names})) FROM `{table}`', db, binary=True)
            digest = hashlib.sha256(b'\n'.join(sorted(rows.splitlines()))).hexdigest()
            sums = {}
            for name, kind in cols:
                if kind in ('tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double'):
                    sums[name] = query(f'SELECT COALESCE(SUM(`{name}`),0) FROM `{table}`', db)
            output[table] = {'rows': int(query(f'SELECT COUNT(*) FROM `{table}`', db)),
                             'original_columns_sha256': digest, 'sums': sums}
        return output

    before = audit()
    (report_dir / 'before.json').write_text(json.dumps(before, indent=2))
    engines = query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{db}' AND ENGINE='MyISAM'").splitlines()
    for table in engines:
        query(f'ALTER TABLE `{table}` ENGINE=InnoDB', db)
    command = docker + ['run', '--rm', '--network', 'samy-release_application', '--read-only',
        '--tmpfs', '/tmp', '--tmpfs', '/app/bootstrap/cache:mode=1777',
        '--tmpfs', '/app/storage:mode=1777']
    isolated = {'APP_ENV': 'testing', 'APP_DEBUG': 'false', 'APP_KEY': env['APP_KEY'],
        'DB_CONNECTION': 'mysql', 'DB_HOST': 'mysql', 'DB_DATABASE': db,
        'DB_COLLATION': 'utf8mb4_unicode_ci', 'DB_CHARSET': 'utf8mb4',
        'APP_TIMEZONE': env.get('APP_TIMEZONE', 'UTC'),
        'DB_USERNAME': db, 'DB_PASSWORD': password, 'MAIL_MAILER': 'log',
        'LOG_CHANNEL': 'stderr', 'QUEUE_CONNECTION': 'database',
        'NOTIFICATIONS_PUSH_ENABLED': 'false', 'NOTIFICATIONS_LAYAWAY_SCAN_ENABLED': 'false',
        'SAMY_DELETE_IMAGES_ON_SALE': 'false'}
    (report_dir / 'migration.private.env').write_text('\n'.join(key + '=' + value for key, value in isolated.items()) + '\n')
    for key, value in isolated.items():
        command += ['-e', key + '=' + value]
    command += ['--entrypoint', 'sh', 'samy-release-app:' + env.get('SAMY_RELEASE_TAG', '20261101'), '-c',
        'mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views; '
        'php artisan migrate --force && php artisan migrate --force']
    result = subprocess.run(command, capture_output=True)
    (report_dir / 'migration.private.log').write_bytes(result.stdout + result.stderr)
    if result.returncode:
        raise SystemExit('Migration failed; original snapshot retained; inspect private log')
    after = audit()
    (report_dir / 'after.json').write_text(json.dumps(after, indent=2))
    changed_rows, changed_sums, changed_values = [], [], []
    for table, previous in before.items():
        if table == 'migrations':
            continue
        current = after[table]
        if previous['rows'] != current['rows']:
            changed_rows.append(table)
        if previous['original_columns_sha256'] != current['original_columns_sha256']:
            changed_values.append(table)
        for column, value in previous['sums'].items():
            if decimal.Decimal(value) != decimal.Decimal(current['sums'][column]):
                changed_sums.append(table + '.' + column)
    result = {'backup_sha256': args.sha256, 'original_tables': len(tables),
        'numeric_columns': sum(len(v['sums']) for v in before.values()),
        'converted_myisam_tables': engines, 'changed_row_counts': changed_rows,
        'changed_numeric_sums': changed_sums, 'changed_original_values': changed_values,
        'application_connected': False, 'workers_started': False, 'outbound_network': False}
    (report_dir / 'comparison.json').write_text(json.dumps(result, indent=2))
    print(json.dumps(result))
    if changed_rows or changed_sums or changed_values:
        raise SystemExit(1)


if __name__ == '__main__':
    main()
