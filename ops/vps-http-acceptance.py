#!/usr/bin/env python3
"""Real HTTP acceptance against the private, synthetic VPS fixture only."""
import json, urllib.request, urllib.error, uuid, pathlib, datetime, http.cookiejar, argparse
parser=argparse.ArgumentParser()
parser.add_argument('--release-preview',action='store_true')
args=parser.parse_args()
ROOT=pathlib.Path('/home/angel/samy-release-20261101' if args.release_preview else '/home/angel/samy-vps-qa-20261101')
assert pathlib.Path.cwd()==ROOT
private=dict(x.split('=',1) for x in (ROOT/('.env.release.private' if args.release_preview else '.env.staging.private')).read_text().splitlines() if '=' in x)
if args.release_preview:
    assert private['SAMY_RELEASE_MODE']=='preview' and private['DB_DATABASE']=='samy_release_preview' and private['APP_ENV']=='staging'
BASE='http://127.0.0.1:'+('18181' if args.release_preview else '18180')
checks=[]
def check(name, condition):
    checks.append({'check':name,'passed':bool(condition)})
    if not condition: raise AssertionError(name)
def call(path, token=None, method='GET', payload=None, key=None, statuses=(200,)):
    headers={'Accept':'application/json'}
    if token: headers['Authorization']='Bearer '+token
    if key: headers['Idempotency-Key']=key
    data=None
    if payload is not None: headers['Content-Type']='application/json'; data=json.dumps(payload).encode()
    req=urllib.request.Request(BASE+path,data=data,headers=headers,method=method)
    try: r=urllib.request.urlopen(req,timeout=30)
    except urllib.error.HTTPError as e: r=e
    body=r.read()
    check(method+' '+path+' status '+str(r.status),r.status in statuses)
    return json.loads(body) if body else {}, r.headers

def main():
    call('/api/products',statuses=(401,))
    tokens={}
    for role in ['gerente','cajero']:
        j,_=call('/api/login',method='POST',payload={'email':role+'@vps.samy.test','password':private['QA_PASSWORD']})
        tokens[role]=j['data']['token']
        me,_=call('/api/me',tokens[role]);check(role+' role',role in me['data']['roles'])
    m=tokens['gerente']; c=tokens['cajero']
    for path in ['/api/capabilities','/api/products','/api/customers','/api/mobile/products','/api/mobile/customers','/api/mobile/sales','/api/mobile/layaways','/api/mobile/product-labels']:
        call(path,c)
    for path in ['/api/mobile/users','/api/mobile/settings','/api/mobile/reports','/api/mobile/dashboard','/api/mobile/catalogs/categories']:
        call(path,c,statuses=(403,));call(path,m)
    for path in ['/api/mobile/products/create','/api/mobile/products/1/edit','/api/mobile/products/1','/api/mobile/customers/create','/api/mobile/catalog-slides','/api/mobile/coupons','/api/mobile/notifications/email-settings','/api/mobile/cash-cuts']:
        call(path,m)
    scan,_=call('/api/mobile/products?q=QVP-000002',c)
    check('variant scan gives one parent',len(scan['data']['products']['data'])==1)
    customer,_=call('/api/mobile/customers',m,'POST',{'name':'QA VPS HTTP Cliente','phone':'5500001102','active':True},'vps-qa-20261101-customer-01')
    retry,h=call('/api/mobile/customers',m,'POST',{'name':'QA VPS HTTP Cliente','phone':'5500001102','active':True},'vps-qa-20261101-customer-01')
    check('customer replay identical',customer==retry and h.get('Idempotency-Replayed')=='true')
    payload={'customer_id':1,'items':[{'variant_id':1,'qty':1}],'payments':[{'method':'cash','amount':99},{'method':'card','amount':100}],'dinero_recibido':200}
    sale,_=call('/api/sales',c,'POST',payload,'vps-qa-20261101-sale-01',(201,))
    repeat,h=call('/api/sales',c,'POST',payload,'vps-qa-20261101-sale-01',(201,))
    check('sale replay identical',sale==repeat and h.get('Idempotency-Replayed')=='true')
    sid=sale['data']['sale']['id'];check('sale total',sale['data']['sale']['total']==199)
    changed={**payload,'dinero_recibido':300}
    call('/api/sales',c,'POST',changed,'vps-qa-20261101-sale-01',(409,))
    call('/api/sales/'+str(sid)+'/ticket',c)
    call('/api/mobile/sales/'+str(sid),c)
    p={'customer_id':1,'items':[{'variant_id':2,'qty':1}],'anticipo_inicial':50,'metodo_anticipo':'cash','fecha_vencimiento':(datetime.date.today()+datetime.timedelta(days=15)).isoformat(),'observaciones':'QA VPS sintético'}
    lay,_=call('/api/layaways',c,'POST',p,'vps-qa-20261101-layaway-01',(201,))
    repeat,h=call('/api/layaways',c,'POST',p,'vps-qa-20261101-layaway-01',(201,))
    check('layaway replay identical',lay==repeat and h.get('Idempotency-Replayed')=='true')
    lid=lay['data']['id'];check('layaway initial balance',lay['data']['saldo_restante']==149)
    pay={'amount':49,'method':'transfer','reference':'SIM-VPS-ABONO-01','auto_liquidate':False}
    paid,_=call(f'/api/layaways/{lid}/payments',c,'POST',pay,'vps-qa-20261101-payment-01',(201,))
    repeat,h=call(f'/api/layaways/{lid}/payments',c,'POST',pay,'vps-qa-20261101-payment-01',(201,))
    check('payment replay identical',paid==repeat and h.get('Idempotency-Replayed')=='true')
    check('layaway final balance',paid['data']['layaway']['saldo_restante']==100)
    call(f'/api/mobile/layaways/{lid}',c)
    today=datetime.date.today().isoformat()
    call(f'/api/reports?from={today}&to={today}',m)
    call('/api/cash-cuts/preview',m,'POST',{'date':today})
    # Session-backed web pages use the real login form and CSRF token.
    import re,urllib.parse
    jar=http.cookiejar.CookieJar(); web=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    page=web.open(BASE+'/login').read().decode()
    csrf=re.search(r'name="csrf-token" content="([^"]+)"',page)
    check('web login csrf present',csrf is not None)
    req=urllib.request.Request(BASE+'/login',data=urllib.parse.urlencode({'email':'gerente@vps.samy.test','password':private['QA_PASSWORD'],'_token':csrf.group(1)}).encode(),headers={'Referer':BASE+'/login'})
    response=web.open(req,timeout=30);check('web login redirects dashboard',response.status==200 and '/login' not in response.url)
    paths=['/dashboard','/pos','/products','/products/create','/products/1/edit','/products/bulk','/customers','/sales','/layaways','/layaways/create','/reports','/cash-cuts','/users','/settings']
    for path in paths:
        r=web.open(BASE+path,timeout=30);check('web '+path,r.status==200 and '/login' not in r.url)
    for path in [f'/sales/{sid}/ticket?width=58',f'/sales/{sid}/ticket?width=80']:
        # URL discovery occurs in routes; unsupported guesses are not accepted.
        pass
    products,_=call('/api/products',c)
    variants={v['id']:v['stock'] for product in products['data'] for v in product['variants']}
    check('sale and layaway stock each decremented once',variants[1]==19 and variants[2]==19)
    for role,t in tokens.items():
        call('/api/logout',t,'POST',{})
        call('/api/me',t,statuses=(401,))
    (ROOT/'qa-browser-access.private.json').write_text(json.dumps({'email':'gerente@vps.samy.test','password':private['QA_PASSWORD']}))
    (ROOT/'qa-browser-access.private.json').chmod(0o600)

try:
    main()
except Exception as e:
    checks.append({'check':'runtime','passed':False,'error_type':type(e).__name__,'message':str(e)[:160]})
    raise
finally:
    (ROOT/'http-acceptance.json').write_text(json.dumps({'checks':checks,'passed':sum(x['passed'] for x in checks),'failed':sum(not x['passed'] for x in checks),'synthetic_only':True},indent=2,ensure_ascii=False))
    print(json.dumps({'passed':sum(x['passed'] for x in checks),'failed':sum(not x['passed'] for x in checks)}))
