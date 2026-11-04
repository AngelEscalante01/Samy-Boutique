#!/usr/bin/env python3
"""Quarantine legacy notifications and require login at the approved cutover.

Preserves queued payloads/history and every financial row. Default is dry run.
The persisted plan fixes ID boundaries so retries cannot select new records.
The preview option exists solely for a synthetic migration rehearsal.
"""
import argparse
import hashlib
import json
import os
import pathlib
import subprocess

parser = argparse.ArgumentParser()
parser.add_argument('--preview', action='store_true')
parser.add_argument('--execute', action='store_true')
args = parser.parse_args()
os.umask(0o077)
base = pathlib.Path.cwd()
env = dict(line.split('=', 1) for line in (base / ('.env.release.private' if args.preview else '.env.production.private')).read_text().splitlines() if '=' in line)
expected = ('preview', 'samy_release_preview', 'samy-release') if args.preview else ('live', 'samy_production', 'samy-production')
if (env['SAMY_RELEASE_MODE'], env['DB_DATABASE'], env.get('SAMY_COMPOSE_PROJECT', 'samy-release')) != expected:
    raise SystemExit('Unexpected mode/schema/project')
state = base / ('private-audit/preview-cutover' if args.preview else 'release-state')
state.mkdir(parents=True, exist_ok=True)
if not args.preview and not (state / 'cutover-approved').is_file():
    raise SystemExit('Final cutover approval required')
docker = ['sudo', '-n', 'docker']
containers = subprocess.check_output(docker + ['ps', '-q', '--filter', 'label=com.docker.compose.project=' + expected[2], '--filter', 'label=com.docker.compose.service=mysql'], text=True).split()
if len(containers) != 1:
    raise SystemExit('Exactly one dedicated database required')
for role in ['app', 'worker', 'scheduler', 'push_worker']:
    # Preview app may remain open; commercial services must not start first.
    if args.preview and role == 'app':
        continue
    running = subprocess.check_output(docker + ['ps', '-q', '--filter', 'label=com.docker.compose.project=' + expected[2], '--filter', 'label=com.docker.compose.service=' + role], text=True).strip()
    if running:
        raise SystemExit('Stop the target application and background jobs before cutover preparation')

def query(sql):
    result = subprocess.run(docker + ['exec', '-i', '-e', 'MYSQL_PWD=' + env['RELEASE_DB_ROOT_PASSWORD'], containers[0],
        'mariadb', '-uroot', '-N', '-B', '--raw', env['DB_DATABASE']], input=sql.encode(), capture_output=True)
    if result.returncode:
        (state / 'runtime-sql-error.private.log').write_bytes(result.stderr)
        raise RuntimeError('Runtime preparation SQL failed; inspect private log')
    return result.stdout.decode().strip()

completed = state / 'runtime-prepared.json'
if completed.exists():
    previous = json.loads(completed.read_text())
    if previous['database'] != env['DB_DATABASE']:
        raise SystemExit('Completion marker belongs to a different schema')
    print(json.dumps({'already_prepared': True, 'database': env['DB_DATABASE']}))
    raise SystemExit(0)
plan_path = state / 'runtime-plan.private.json'
tables = ['jobs', 'notification_devices', 'personal_access_tokens', 'notification_deliveries', 'notification_dispatches', 'layaways']
if plan_path.exists():
    plan = json.loads(plan_path.read_text())
    if plan['database'] != env['DB_DATABASE']:
        raise SystemExit('Plan belongs to a different schema')
else:
    plan = {'database': env['DB_DATABASE'], 'synthetic_only': args.preview, 'boundaries': {}, 'jobs': []}
    for table in tables:
        plan['boundaries'][table] = int(query(f'SELECT COALESCE(MAX(id),0) FROM `{table}`'))
    for row in query("SELECT id,queue,SHA2(payload,256) FROM jobs ORDER BY id").splitlines():
        identifier, queue, digest = row.split('\t')
        plan['jobs'].append({'id': int(identifier), 'original_queue': queue, 'payload_sha256': digest})
    if args.execute:
        plan_path.write_text(json.dumps(plan, indent=2))
        plan_path.chmod(0o600)
if not args.execute:
    print(json.dumps({'dry_run': True, 'database': env['DB_DATABASE'], 'synthetic_only': args.preview,
        'legacy_jobs_to_preserve': len(plan['jobs']), 'boundaries': plan['boundaries']}))
    raise SystemExit(0)
limits = plan['boundaries']
threshold = max(0, int(env.get('NOTIFICATIONS_LOW_STOCK_THRESHOLD', '3')))
query(f"""START TRANSACTION;
UPDATE jobs SET queue='legacy-cutover-20261101' WHERE id<={limits['jobs']};
UPDATE notification_devices SET active=0,updated_at=NOW() WHERE id<={limits['notification_devices']};
UPDATE personal_access_tokens SET expires_at=NOW() WHERE id<={limits['personal_access_tokens']};
UPDATE notification_deliveries SET status='cancelled',last_error_code='cutover_legacy_device',updated_at=NOW()
WHERE id<={limits['notification_deliveries']} AND status IN ('pending','error_temporal','sending');
UPDATE notification_dispatches SET status='processed',processed_at=NOW(),updated_at=NOW()
WHERE id<={limits['notification_dispatches']} AND status='pending';
INSERT INTO notification_inventory_states(product_variant_id,state,transition_count,updated_at)
SELECT id,CASE WHEN stock<=0 THEN 'out' WHEN stock<={threshold} THEN 'low' ELSE 'normal' END,0,NOW()
FROM product_variants WHERE active=1
ON DUPLICATE KEY UPDATE state=VALUES(state),updated_at=VALUES(updated_at);
COMMIT;""")
for job in plan['jobs']:
    actual = query(f"SELECT queue,SHA2(payload,256) FROM jobs WHERE id={job['id']}")
    if actual != 'legacy-cutover-20261101\t' + job['payload_sha256']:
        raise RuntimeError('Legacy payload preservation check failed')
if not args.preview:
    # Enable scheduled expiry notices only for records created after the final
    # snapshot. Existing layaways remain visible without a historical burst.
    path = base / '.env.production.private'
    replacements = {'NOTIFICATIONS_LAYAWAY_SCAN_ENABLED': 'true',
        'NOTIFICATIONS_LAYAWAY_SCAN_MIN_ID': str(limits['layaways'])}
    lines = []
    for line in path.read_text().splitlines():
        key = line.split('=', 1)[0]
        lines.append(key + '=' + replacements.pop(key) if key in replacements else line)
    lines += [key + '=' + value for key, value in replacements.items()]
    temporary_env = path.with_suffix('.private.tmp')
    temporary_env.write_text('\n'.join(lines) + '\n')
    temporary_env.chmod(0o600)
    temporary_env.replace(path)
result = {'database': env['DB_DATABASE'], 'synthetic_only': args.preview,
    'legacy_jobs_preserved': len(plan['jobs']), 'payload_hashes_verified': True,
    'historical_notifications_sent': 0, 'financial_tables_changed': False,
    'inventory_notification_baseline_set': True, 'layaway_scan_min_id': limits['layaways']}
temporary = completed.with_suffix('.tmp')
temporary.write_text(json.dumps(result, indent=2))
temporary.replace(completed)
print(json.dumps(result))
