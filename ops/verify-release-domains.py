#!/usr/bin/env python3
"""Read-only host/HTTPS/SEO checks against the synthetic loopback preview."""
import html
import json
import pathlib
import urllib.error
import urllib.request

env = dict(line.split('=', 1) for line in pathlib.Path('.env.release.private').read_text().splitlines() if '=' in line)
if env['SAMY_RELEASE_MODE'] != 'preview' or env['DB_DATABASE'] != 'samy_release_preview':
    raise SystemExit('Synthetic release preview required')
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None
client = urllib.request.build_opener(NoRedirect)
checks = []
def request(host, path, method='GET'):
    request = urllib.request.Request('http://127.0.0.1:18181' + path,
        headers={'Host': host, 'X-Forwarded-Proto': 'https'}, method=method)
    try:
        response = client.open(request, timeout=20)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.headers, response.read().decode()
def check(name, passed):
    checks.append({'check': name, 'passed': bool(passed)})
    if not passed:
        raise AssertionError(name)
public = 'samyboutique.com.mx'
system = 'sistema.samyboutique.com.mx'
status, headers, body = request(public, '/')
check('public root opens catalog', status == 200 and '"component":"Public/Catalog/Index"' in html.unescape(body).replace('\\/', '/'))
check('public canonical HTTPS root', 'https://samyboutique.com.mx/' in body)
status, headers, body = request(public, '/catalogo?q=QVP')
check('legacy catalog path preserves query', status == 301 and headers['Location'] == 'https://' + public + '/?q=QVP')
status, headers, body = request('www.' + public, '/?q=QVP')
check('www canonical redirect', status == 301 and headers['Location'] == 'https://' + public + '/?q=QVP')
status, headers, body = request(public, '/login')
check('public login goes to system', status == 308 and headers['Location'] == 'https://' + system + '/login')
check('public API is not exposed', request(public, '/api/products')[0] == 404)
status, headers, body = request(system, '/')
check('system root opens login', status == 302 and headers['Location'] == 'https://' + system + '/login')
status, headers, body = request(system, '/login')
check('system login is not indexed', status == 200 and headers['X-Robots-Tag'] == 'noindex, nofollow')
status, headers, body = request(system, '/catalogo')
check('system catalog goes to public host', status == 301 and headers['Location'] == 'https://' + public + '/')
status, headers, body = request(public, '/robots.txt')
check('public robots points to sitemap', status == 200 and 'Sitemap: https://' + public + '/sitemap.xml' in body)
status, headers, body = request(public, '/sitemap.xml')
check('sitemap contains only public URLs', status == 200 and 'https://' + public + '/' in body and system not in body)
result = {'passed': len(checks), 'failed': 0, 'synthetic_only': True, 'public_dns_changed': False, 'checks': checks}
pathlib.Path('domain-acceptance.json').write_text(json.dumps(result, indent=2))
print(json.dumps({'passed': len(checks), 'failed': 0, 'public_dns_changed': False}))
