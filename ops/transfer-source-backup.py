#!/usr/bin/env python3
"""Read only the explicitly authorized Samy backup through a temporary FTPS user."""
import argparse
import ftplib
import hashlib
import json
import os
import pathlib
import ssl

parser = argparse.ArgumentParser()
parser.add_argument('--config', type=pathlib.Path, default=pathlib.Path('.private/ftps-transfer.json'))
parser.add_argument('--output', type=pathlib.Path, default=pathlib.Path('private-source'))
args = parser.parse_args()
os.umask(0o077)
config = json.loads(args.config.read_text())
if config['host'] != 'svgs229.serverneubox.com.mx' or config['username'] != 'samymove20261101@samy-boutique.makedweb.com':
    raise SystemExit('Unexpected source transfer account')
args.output.mkdir(mode=0o700, parents=True, exist_ok=True)
name = 'samy-release-source-20261101.tar.gz'
partial, final = args.output / (name + '.part'), args.output / name
for attempt in range(3):
    ftp = ftplib.FTP_TLS(context=ssl.create_default_context())
    ftp.connect(config['host'], 21, timeout=60)
    ftp.login(config['username'], config['password'])
    ftp.prot_p()  # Both control and file bytes require verified TLS.
    ftp.voidcmd('TYPE I')
    expected = ftp.size(name)
    offset = partial.stat().st_size if partial.exists() else 0
    if final.exists() and final.stat().st_size == expected:
        ftp.quit()
        break
    if offset > expected:
        raise SystemExit('Unexpected partial size; refusing to overwrite')
    print(json.dumps({'transfer': 'FTPS', 'tls': ftp.sock.version(), 'expected_bytes': expected,
                      'resume_bytes': offset}), flush=True)
    last = [offset // (100 * 1024 * 1024)]
    try:
        with partial.open('ab') as stream:
            def block(data):
                stream.write(data)
                progress = stream.tell() // (100 * 1024 * 1024)
                if progress > last[0]:
                    last[0] = progress
                    print(json.dumps({'downloaded_mb': stream.tell() // (1024 * 1024)}), flush=True)
            if offset < expected:
                ftp.retrbinary('RETR ' + name, block, blocksize=262144, rest=offset if offset else None)
        ftp.quit()
    except (OSError, ftplib.Error) as error:
        print(json.dumps({'attempt': attempt + 1, 'error_type': type(error).__name__}), flush=True)
        ftp.close()
        if not partial.exists() or partial.stat().st_size != expected:
            continue
    if partial.stat().st_size == expected:
        partial.replace(final)
        break
else:
    raise SystemExit('Transfer incomplete; durable partial retained for explicit resume')
digest = hashlib.sha256()
with final.open('rb') as stream:
    for data in iter(lambda: stream.read(1024 * 1024), b''):
        digest.update(data)
report = {'file': name, 'bytes': final.stat().st_size, 'sha256': digest.hexdigest(), 'ftps_tls_validated': True}
(args.output / 'source-backup-transfer.json').write_text(json.dumps(report, indent=2))
print(json.dumps(report), flush=True)
