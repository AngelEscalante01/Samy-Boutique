#!/usr/bin/env python3
"""Package current code through an explicit allowlist; never copy local secrets."""
import argparse
import hashlib
import json
import pathlib
import tarfile

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=pathlib.Path, required=True)
args = parser.parse_args()
root = pathlib.Path(__file__).resolve().parent.parent
output = args.output.resolve()
files = set()
for name in ['app', 'bootstrap', 'config', 'database/migrations', 'database/seeders', 'database/factories',
             'resources', 'routes', 'docker/release', 'ops', 'tests']:
    files.update(path for path in (root / name).rglob('*') if path.is_file())
for name in ['artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
             'vite.config.js', 'tailwind.config.js', 'postcss.config.js', 'phpunit.xml',
             'compose.release.yaml', 'compose.publish.yaml', 'compose.migration.yaml']:
    files.add(root / name)
for pattern in ['*.png', '*.ico', '*.html', 'index.php', 'robots.txt', 'images/product-placeholder.svg']:
    files.update((root / 'public').glob(pattern))
files.update((root / 'docs').glob('*2026-11-01.md'))
excluded = {'__pycache__', 'node_modules', 'vendor', '.git'}
approved = []
for path in sorted(files):
    relative = path.relative_to(root)
    if path.is_symlink() or any(part in excluded for part in relative.parts):
        continue
    if relative.parts[:2] == ('bootstrap', 'cache'):
        continue
    if path.name.startswith('.env') or '.private' in path.name or path.suffix in ['.pem', '.jks', '.keystore', '.pyc']:
        continue
    if any(value in path.name for value in ['credentials', 'service-account']):
        continue
    approved.append(path)
manifest = {str(path.relative_to(root)): hashlib.sha256(path.read_bytes()).hexdigest() for path in approved}
output.parent.mkdir(parents=True, exist_ok=True)
with tarfile.open(output, 'w:gz') as archive:
    for path in approved:
        archive.add(path, arcname=str(path.relative_to(root)), recursive=False)
report = {'files': len(approved), 'sha256': hashlib.sha256(output.read_bytes()).hexdigest(), 'source_files': manifest}
output.with_name('release-source-manifest.json').write_text(json.dumps(report, indent=2))
print(json.dumps({'files': len(approved), 'bytes': output.stat().st_size, 'sha256': report['sha256']}))
