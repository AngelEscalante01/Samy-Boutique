#!/usr/bin/env python3
"""Encrypted Restic backup of the dedicated Samy DB, local files and R2 media.

Production runs only after cutover approval. Preview uses synthetic DB/media.
Background jobs stop gracefully during capture and always resume in finally.
No automatic pruning or remote photo deletion is performed.
"""
import argparse
import datetime
import fcntl
import gzip
import hashlib
import json
import os
import pathlib
import signal
import subprocess
import tempfile

RESTIC_IMAGE = 'restic/restic:0.19.1'
parser = argparse.ArgumentParser()
parser.add_argument('--release-directory', type=pathlib.Path, default=pathlib.Path.cwd())
parser.add_argument('--preview', action='store_true')
parser.add_argument('--initialize-repository', action='store_true')
parser.add_argument('--verify-restore', action='store_true')
parser.add_argument('--resume-only', action='store_true')
parser.add_argument('--tools-directory', type=pathlib.Path, default=pathlib.Path(__file__).resolve().parent)
args = parser.parse_args()
os.umask(0o077)
root = args.release_directory.resolve()
env_path = root / ('.env.release.private' if args.preview else '.env.production.private')
values = dict(line.split('=', 1) for line in env_path.read_text().splitlines() if line and not line.startswith('#'))
expected = ('preview', 'samy_release_preview', 'samy-release') if args.preview else ('live', 'samy_production', 'samy-production')
if (values['SAMY_RELEASE_MODE'], values['DB_DATABASE'], values.get('SAMY_COMPOSE_PROJECT', 'samy-release')) != expected:
    raise SystemExit('Unexpected backup mode/schema/project; backup schemas forbidden')
if not args.preview and not (root / 'release-state/cutover-approved').is_file():
    raise SystemExit('Production cutover not approved')
project = expected[2]
directory = root / 'private-backups'
directory.mkdir(mode=0o700, exist_ok=True)
lock = (directory / 'backup.lock').open('a')
fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
docker = ['docker'] if os.geteuid() == 0 else ['sudo', '-n', 'docker']

def run(command, **kwargs):
    return subprocess.run(command, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, **kwargs)

def file_hash(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def container(service, running=False):
    command = docker + ['ps', '-q' if running else '-aq', '--filter', 'label=com.docker.compose.project=' + project,
                        '--filter', 'label=com.docker.compose.service=' + service]
    found = run(command).stdout.decode().split()
    if len(found) > 1:
        raise RuntimeError('Ambiguous service container')
    return found[0] if found else None

resume_path = directory / ('resume-' + project + '.private.json')

def resume():
    if not resume_path.exists():
        return
    state = json.loads(resume_path.read_text())
    if state['project'] != project:
        raise RuntimeError('Backup recovery belongs to another project')
    for role, identifier in state['containers'].items():
        # Never operate on another project or a replacement container.
        if container(role) != identifier:
            raise RuntimeError('Backup service container changed; inspect private recovery state')
        paused = run(docker + ['inspect', identifier, '--format', '{{.State.Paused}}']).stdout.strip()
        if paused == b'true':
            run(docker + ['unpause', identifier])
        if role != 'app':
            run(docker + ['start', identifier])
    resume_path.unlink()

resume()  # Recover an interrupted capture before beginning a new one.
if args.resume_only:
    print(json.dumps({'project': project, 'backup_services_resumed': True}))
    raise SystemExit(0)

mysql = container('mysql')
if not mysql:
    raise SystemExit('Dedicated Samy database is not available')
image = 'samy-release-app:' + values.get('SAMY_RELEASE_TAG', '20261101')
network = project + '_egress'
media = directory / ('synthetic-media' if args.preview else 'r2-media')
media.mkdir(mode=0o700, exist_ok=True)
restic_env = root / '.env.restic.private'
password_file = root / '.private/restic-password'
if not restic_env.is_file() or not password_file.is_file():
    raise SystemExit('Private backup repository/password configuration missing')
restore = directory / 'last-verified-restore'
roles = [identifier for role in ['worker', 'scheduler', 'push_worker'] if (identifier := container(role, running=True))]
application = container('app', running=True)
if not application:
    raise SystemExit('Running application required for a coordinated capture')
if run(docker + ['inspect', application, '--format', '{{.State.Paused}}']).stdout.strip() == b'true':
    raise SystemExit('Application was already paused outside this backup')

def restic(parameters, snapshot=None):
    command = docker + ['run', '--rm', '--network', network, '--env-file', str(restic_env),
        '-v', str(password_file) + ':/run/secrets/password:ro',
        '-v', project + '-restic-cache:/root/.cache/restic']
    if snapshot:
        command += ['-v', str(snapshot) + ':/snapshot:ro', '-v', str(media) + ':/r2-media:ro']
    if restore.exists():
        command += ['-v', str(restore) + ':/restore']
    return run(command + [RESTIC_IMAGE, '-o', 's3.bucket-lookup=path', '-o', 's3.region=auto'] + parameters)

def mirror():
    command = docker + ['run', '--rm', '--user', str(os.geteuid()) + ':' + str(os.getegid()),
        '--network', network, '--env-file', str(root / '.env.r2-production.private'),
        '-v', str(args.tools_directory.resolve() / 'release-media-snapshot.php') + ':/media.php:ro',
        '-v', str(media) + ':/snapshot', '--entrypoint', 'php', image, '/media.php']
    if args.preview:
        command.append('--synthetic-only')
    run(command)

def interrupted(signum, frame):
    raise RuntimeError('Backup interrupted')

signal.signal(signal.SIGTERM, interrupted)
signal.signal(signal.SIGINT, interrupted)
try:
    if args.initialize_repository:
        restic(['init'])  # Explicit opt-in; never replaces an existing repository.
    # Download the large first pass while the application remains available.
    mirror()
    coordinated = {'app': application}
    coordinated.update({role: identifier for role in ['worker', 'scheduler', 'push_worker'] if (identifier := container(role, running=True))})
    roles = [identifier for role, identifier in coordinated.items() if role != 'app']
    resume_path.write_text(json.dumps({'project': project, 'containers': coordinated}, indent=2))
    for role, identifier in coordinated.items():
        if role != 'app':
            run(docker + ['stop', '--time', '615' if role == 'push_worker' else '75', identifier])
    # Quiesce web mutations as well as workers: customer/carousel replacement
    # can delete files synchronously. MVCC excludes unfinished transactions.
    # Nginx remains running; requests wait during this short capture window.
    run(docker + ['pause', application])
    with tempfile.TemporaryDirectory(prefix='capture-', dir=directory) as temporary:
        capture = pathlib.Path(temporary)
        mirror()
        dump_path = capture / 'database.sql.gz'
        dump = subprocess.Popen(docker + ['exec', '-e', 'MYSQL_PWD=' + values['RELEASE_DB_ROOT_PASSWORD'], mysql,
            'mariadb-dump', '-uroot', '--single-transaction', '--quick', '--skip-lock-tables', '--hex-blob',
            '--default-character-set=utf8mb4', values['DB_DATABASE']], stdout=subprocess.PIPE, stderr=subprocess.DEVNULL)
        with gzip.open(dump_path, 'wb') as output:
            for block in iter(lambda: dump.stdout.read(1024 * 1024), b''):
                output.write(block)
        if dump.wait() != 0:
            raise RuntimeError('Database backup failed')
        # A second pass captures uploads referenced by the DB snapshot; queued
        # physical media deletions remain paused until capture is complete.
        mirror()
        storage = container('app')
        if not storage:
            raise RuntimeError('App storage volume is unavailable')
        result = run(docker + ['inspect', storage, '--format', '{{json .Mounts}}'])
        mount = next(m for m in json.loads(result.stdout) if m['Destination'] == '/app/storage')
        with (capture / 'local-storage.tar.gz').open('wb') as output:
            subprocess.run(docker + ['run', '--rm', '--user', '0', '--network', 'none',
                '-v', mount['Name'] + ':/source:ro', '--entrypoint', 'tar', image,
                '-czf', '-', '-C', '/source', 'app'], check=True, stdout=output, stderr=subprocess.PIPE)
        configuration = capture / 'configuration'
        configuration.mkdir()
        (configuration / 'environment.private').write_bytes(env_path.read_bytes())
        for name in ['compose.release.yaml', 'compose.publish.yaml']:
            (configuration / name).write_bytes((root / name).read_bytes())
        source = root / 'release-source.tar.gz'
        source_manifest = root / 'release-source-manifest.json'
        if not args.preview and (not source.is_file() or not source_manifest.is_file()):
            raise RuntimeError('Reviewed release source package is required for production recovery')
        if source.is_file():
            source_report = json.loads(source_manifest.read_text())
            if file_hash(source) != source_report['sha256']:
                raise RuntimeError('Release source package hash mismatch')
            (configuration / source.name).write_bytes(source.read_bytes())
            (configuration / source_manifest.name).write_bytes(source_manifest.read_bytes())
        if not args.preview:
            (configuration / 'firebase.json').write_bytes((root / 'firebase-prod-service-account.json').read_bytes())
        manifest = {str(path.relative_to(capture)): file_hash(path)
                    for path in capture.rglob('*') if path.is_file()}
        (capture / 'manifest.json').write_text(json.dumps(manifest, indent=2))
        # The private capture/cache is now immutable. Resume before network
        # upload, integrity checks or restore verification can take longer.
        resume()
        tag = 'samy-release-preview' if args.preview else 'samy-production'
        output = restic(['backup', '--json', '--tag', tag, '/snapshot', '/r2-media'], capture)
        messages = [json.loads(line) for line in output.stdout.splitlines() if line.strip()]
        summary = next(message for message in messages if message.get('message_type') == 'summary')
        snapshot = summary['snapshot_id']
        restic(['check', '--read-data'])
        if args.verify_restore:
            restore.mkdir(mode=0o700, exist_ok=True)
            restic(['restore', snapshot, '--target', '/restore'])
            for name, expected_hash in manifest.items():
                restored = restore / 'snapshot' / name
                if file_hash(restored) != expected_hash:
                    raise RuntimeError('Restored file hash mismatch')
            for source in media.glob('*/manifest.json'):
                mapping = json.loads(source.read_text())
                for metadata in mapping.values():
                    restored = restore / 'r2-media' / source.parent.name / metadata['file']
                    if file_hash(restored) != metadata['sha256']:
                        raise RuntimeError('Restored media hash mismatch')
        report = {'completed_at': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'project': project,
            'snapshot_id': snapshot, 'encrypted_offsite': True, 'repository_checked_with_read_data': True,
            'restore_hashes_verified': args.verify_restore, 'synthetic_only': args.preview,
            'web_mutations_quiesced_during_capture': True,
            'files_processed': summary.get('total_files_processed'), 'automatic_photo_deletions': False}
        (directory / 'latest.json').write_text(json.dumps(report, indent=2))
        print(json.dumps(report))
finally:
    resume()
