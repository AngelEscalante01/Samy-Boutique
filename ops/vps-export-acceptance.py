#!/usr/bin/env python3
import pathlib,json,urllib.request,urllib.parse,datetime,zipfile,io
root=pathlib.Path('/home/angel/samy-vps-qa-20261101');assert pathlib.Path.cwd()==root
private=dict(x.split('=',1) for x in (root/'.env.staging.private').read_text().splitlines() if '=' in x)
base='http://127.0.0.1:18180';payload=json.dumps({'email':'gerente@vps.samy.test','password':private['QA_PASSWORD']}).encode()
r=urllib.request.urlopen(urllib.request.Request(base+'/api/login',data=payload,headers={'Content-Type':'application/json','Accept':'application/json'}),timeout=20)
token=json.load(r)['data']['token'];checks=[]
try:
 for fmt in ['pdf','xlsx']:
  query=urllib.parse.urlencode({'from':datetime.date.today().isoformat(),'to':datetime.date.today().isoformat()})
  req=urllib.request.Request(base+'/api/mobile/reports/export/'+fmt+'?'+query,headers={'Authorization':'Bearer '+token})
  r=urllib.request.urlopen(req,timeout=40);data=r.read()
  valid=data.startswith(b'%PDF-') if fmt=='pdf' else data.startswith(b'PK') and zipfile.ZipFile(io.BytesIO(data)).testzip() is None
  assert r.status==200 and valid
  checks.append({'format':fmt,'http':r.status,'bytes':len(data),'valid_container':valid,'content_type':r.headers.get('Content-Type')})
finally:
 urllib.request.urlopen(urllib.request.Request(base+'/api/logout',data=b'{}',headers={'Content-Type':'application/json','Authorization':'Bearer '+token}),timeout=20).read()
(root/'export-verification.json').write_text(json.dumps(checks,indent=2));print(json.dumps(checks))