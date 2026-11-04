#!/usr/bin/env python3
"""Prepare a frozen source and manifest for CLI migration; starts no app/worker."""
import argparse
import hashlib
import json
import os
import pathlib
import re
import shutil
import tarfile

parser = argparse.ArgumentParser()
parser.add_argument('--database', required=True)
parser.add_argument('--source-sha256', required=True)
args = parser.parse_args()
if not re.fullmatch(r'samy_migration_[0-9]{8}[a-z0-9_]*', args.database):
    raise SystemExit('Dedicated rehearsal schema required')
os.umask(0o077)
base = pathlib.Path.cwd()
audit = base / 'private-audit' / args.database
comparison = json.loads((audit / 'comparison.json').read_text())
if any(comparison[key] for key in ['changed_row_counts', 'changed_numeric_sums', 'changed_original_values']):
    raise SystemExit('Original data must be reconciled first')
archive = base / 'private-source/samy-release-source-20261101.tar.gz'
with archive.open('rb') as stream:
    digest = hashlib.file_digest(stream, 'sha256').hexdigest()
if digest != args.source_sha256:
    raise SystemExit('File archive hash mismatch')
sql = base / 'neubox-current.sql.gz'
if hashlib.sha256(sql.read_bytes()).hexdigest() != comparison['backup_sha256']:
    raise SystemExit('Database backup hash mismatch')
destination = base / 'private-source/extracted-public'
destination.mkdir(mode=0o700, parents=True, exist_ok=True)
count, total = 0, 0
source_key = None
with tarfile.open(archive, 'r|gz') as source:
    for item in source:
        if item.name == 'samy-boutique/.env' and item.isfile():
            text = source.extractfile(item).read().decode()
            source_key = next(line.split('=', 1)[1].strip().strip('\"\'') for line in text.splitlines() if line.startswith('APP_KEY='))
        prefix = 'samy-boutique/storage/app/public/'
        if not item.isfile() or not item.name.startswith(prefix):
            continue
        relative = pathlib.PurePosixPath(item.name[len(prefix):])
        if relative.is_absolute() or '..' in relative.parts:
            raise SystemExit('Unsafe archive member')
        path = destination / str(relative)
        path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        with source.extractfile(item) as stream, path.open('wb') as output:
            shutil.copyfileobj(stream, output, length=1024 * 1024)
        count += 1
        total += item.size
if not source_key or count == 0:
    raise SystemExit('Incomplete source archive')
approval = audit / 'media-approval.json'
approval.write_text(json.dumps({'purpose': 'production-media-copy', 'database': args.database,
    'public_bucket': 'samy-public-prod', 'private_bucket': 'samy-private-prod',
    'source_sha256': comparison['backup_sha256'], 'source_files_sha256': digest}, indent=2))
env = dict(line.split('=', 1) for line in (audit / 'migration.private.env').read_text().splitlines() if '=' in line)
env.update(dict(line.split('=', 1) for line in (base / '.env.r2-production.private').read_text().splitlines() if '=' in line))
env.update({'APP_ENV': 'migration', 'APP_KEY': source_key, 'MEDIA_MIGRATION_ENABLED': 'true',
    'MEDIA_MIGRATION_APPROVAL_FILE': '/migration/approval.json', 'MEDIA_MIGRATION_SOURCE_BACKUP': '/migration/source.sql.gz',
    'SAMY_MIGRATION_UID': str(os.getuid()), 'SAMY_MIGRATION_GID': str(os.getgid()),
    'SAMY_MIGRATION_SOURCE_PUBLIC': str(destination), 'SAMY_MIGRATION_SOURCE_BACKUP': str(sql),
    'SAMY_MIGRATION_APPROVAL_FILE': str(approval)})
release = dict(line.split('=', 1) for line in (base / '.env.release.private').read_text().splitlines() if '=' in line)
env['SAMY_RELEASE_TAG'] = release.get('SAMY_RELEASE_TAG', '20261101')
path = base / '.env.migration.private'
path.write_text('\n'.join(key + '=' + value for key, value in env.items()) + '\n')
path.chmod(0o600)
print(json.dumps({'database': args.database, 'public_source_files': count, 'public_source_bytes': total,
    'source_files_sha256': digest, 'public_bucket': 'samy-public-prod', 'starts_application': False}))
