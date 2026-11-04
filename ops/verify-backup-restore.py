#!/usr/bin/env python3
"""Restore the synthetic offsite backup and compare business rows, CLI only.

Creates a new named schema in the isolated preview DB; never overwrites a
schema, starts a web app/worker or uses commercial data.
"""
import argparse
import gzip
import hashlib
import json
import os
import pathlib
import re
import subprocess

parser = argparse.ArgumentParser()
parser.add_argument('--database', required=True)
args = parser.parse_args()
if not re.fullmatch(r'samy_backup_verify_[0-9]{8}[a-z0-9_]*', args.database):
    raise SystemExit('Dedicated fresh restore schema required')
os.umask(0o077)
base = pathlib.Path.cwd()
env = dict(line.split('=', 1) for line in (base / '.env.release.private').read_text().splitlines() if '=' in line)
report = json.loads((base / 'private-backups/latest.json').read_text())
if (env['SAMY_RELEASE_MODE'], env['DB_DATABASE']) != ('preview', 'samy_release_preview') or not report['synthetic_only'] or not report['restore_hashes_verified']:
    raise SystemExit('Verified synthetic preview backup required')
restored = base / 'private-backups/last-verified-restore/snapshot'
manifest = json.loads((restored / 'manifest.json').read_text())
dump = (restored / 'database.sql.gz').read_bytes()
if hashlib.sha256(dump).hexdigest() != manifest['database.sql.gz']:
    raise SystemExit('Restored SQL hash mismatch')
sql = gzip.decompress(dump)
if re.search(rb'^\s*(USE |CREATE DATABASE|DROP DATABASE)|DEFINER\s*=', sql, re.M | re.I):
    raise SystemExit('Unexpected schema switching in dump')
docker = ['sudo', '-n', 'docker']
container = subprocess.check_output(docker + ['compose', '--env-file', '.env.release.private', '-f', 'compose.release.yaml', 'ps', '-q', 'mysql'], text=True).strip()
if not container:
    raise SystemExit('Dedicated preview DB not available')

def query(statement, database=None):
    command = docker + ['exec', '-i', '-e', 'MYSQL_PWD=' + env['RELEASE_DB_ROOT_PASSWORD'], container,
        'mariadb', '-uroot', '-N', '-B', '--raw']
    if database:
        command.append(database)
    result = subprocess.run(command, input=statement if isinstance(statement, bytes) else statement.encode(), capture_output=True)
    if result.returncode:
        (base / 'private-backups/restore-sql-error.private.log').write_bytes(result.stderr)
        raise RuntimeError('Restore SQL failed; inspect private log')
    return result.stdout

db = args.database
if query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='" + db + "'").strip():
    raise SystemExit('Restore destination already exists; refusing overwrite')
query(f'CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
query(sql, db)
source = env['DB_DATABASE']
tables = query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{source}' ORDER BY TABLE_NAME").decode().splitlines()
actual = query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{db}' ORDER BY TABLE_NAME").decode().splitlines()
if tables != actual:
    raise RuntimeError('Restored table inventory differs')
# Session/queue/token state can change during capture. Compare every row in
# all business tables, including stock, payments, idempotency and image refs.
volatile = {'sessions', 'personal_access_tokens', 'cache', 'cache_locks', 'jobs', 'failed_jobs', 'job_batches'}
compared, rows, differences = 0, 0, []
for table in tables:
    if table in volatile:
        continue
    if not re.fullmatch('[a-zA-Z0-9_]+', table):
        raise RuntimeError('Unexpected identifier')
    columns = query(f"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{source}' AND TABLE_NAME='{table}' ORDER BY ORDINAL_POSITION").decode().splitlines()
    expression = ','.join('`' + name + '`' for name in columns)
    statement = f'SELECT HEX(JSON_ARRAY({expression})) FROM `{table}`'
    previous = sorted(query(statement, source).splitlines())
    recovered = sorted(query(statement, db).splitlines())
    if previous != recovered:
        differences.append(table)
    compared += 1
    rows += len(recovered)
result = {'synthetic_only': True, 'tables_restored': len(tables), 'business_tables_compared': compared,
    'business_rows_compared': rows, 'changed_business_tables': differences,
    'application_connected': False, 'workers_started': False}
(base / 'private-backups/database-restore-verification.json').write_text(json.dumps(result, indent=2))
print(json.dumps(result))
if differences:
    raise SystemExit(1)
