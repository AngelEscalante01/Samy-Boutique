#!/usr/bin/env python3
"""Reconcile a CLI-only migrated copy with the original restored schema."""
import argparse
import hashlib
import json
import os
import pathlib
import re
import subprocess

parser = argparse.ArgumentParser()
parser.add_argument('--original-schema', required=True)
parser.add_argument('--migrated-schema', required=True)
args = parser.parse_args()
for value in [args.original_schema, args.migrated_schema]:
    if not re.fullmatch(r'samy_migration_[0-9]{8}[a-z0-9_]*', value):
        raise SystemExit('Dedicated migration schemas required')
os.umask(0o077)
base = pathlib.Path.cwd()
env = dict(line.split('=', 1) for line in (base / '.env.release.private').read_text().splitlines() if '=' in line)
if env['SAMY_RELEASE_MODE'] != 'preview' or env['DB_DATABASE'] != 'samy_release_preview':
    raise SystemExit('Isolated preview required')
docker = ['sudo', '-n', 'docker']
container = subprocess.check_output(docker + ['compose', '--env-file', '.env.release.private', '-f', 'compose.release.yaml', 'ps', '-q', 'mysql'], text=True).strip()
if not container:
    raise SystemExit('Dedicated database unavailable')

def query(statement, schema):
    result = subprocess.run(docker + ['exec', '-i', '-e', 'MYSQL_PWD=' + env['RELEASE_DB_ROOT_PASSWORD'], container,
        'mariadb', '-uroot', '-N', '-B', '--raw', schema], input=statement.encode(), capture_output=True)
    if result.returncode:
        (base / 'private-audit/media-audit-error.private.log').write_bytes(result.stderr)
        raise RuntimeError('Private audit SQL failed')
    return result.stdout

source, target = args.original_schema, args.migrated_schema
tables = query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{source}' ORDER BY TABLE_NAME", source).decode().splitlines()
changed = []
checked = 0
# Only these original image fields may change during the copy. All financial,
# stock, customer, image ownership and variant assignment fields must match.
media_fields = {'path', 'disk', 'generation', 'sha256', 'bytes', 'content_type', 'source_disk', 'source_path', 'storage_verified_at', 'updated_at'}
for table in tables:
    if table == 'migrations':
        continue
    if not re.fullmatch('[a-zA-Z0-9_]+', table):
        raise RuntimeError('Unexpected identifier')
    columns = query(f"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{source}' AND TABLE_NAME='{table}' ORDER BY ORDINAL_POSITION", source).decode().splitlines()
    if table == 'product_images':
        columns = [column for column in columns if column not in media_fields]
    if table == 'products':
        # ProductImage touches its owner so offline incremental clients receive
        # the new image URL. No other original product value may change.
        columns = [column for column in columns if column != 'updated_at']
    expression = ','.join('`' + column + '`' for column in columns)
    statement = f'SELECT HEX(JSON_ARRAY({expression})) FROM `{table}`'
    original = sorted(query(statement, source).splitlines())
    current = sorted(query(statement, target).splitlines())
    if original != current:
        changed.append(table)
    checked += 1
images = query("SELECT COUNT(*),SUM(disk='r2_public' AND sha256 IS NOT NULL AND storage_verified_at IS NOT NULL),SUM(disk='public') FROM product_images WHERE deleted_at IS NULL", target).decode().strip().split('\t')
derivatives = query('SELECT COUNT(*) FROM product_image_derivatives', target).decode().strip()
result = {'original_tables_compared': checked, 'unexpected_changed_tables': changed,
    'owner_timestamps_refreshed_for_image_sync': int(query(f'SELECT COUNT(*) FROM `{source}`.products o JOIN `{target}`.products n ON o.id=n.id WHERE NOT(o.updated_at <=> n.updated_at)', target).decode().strip()),
    'active_images': int(images[0]), 'verified_r2_images': int(images[1] or 0),
    'unmigrated_active_images': int(images[2] or 0), 'derivatives': int(derivatives),
    'web_application_connected': False, 'workers_started': False}
(base / 'private-audit/media-reconciliation.json').write_text(json.dumps(result, indent=2))
print(json.dumps(result))
if changed or result['unmigrated_active_images'] or result['active_images'] != result['verified_r2_images']:
    raise SystemExit(1)
