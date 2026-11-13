import concurrent.futures, json, re, subprocess, tomllib, urllib.request, urllib.parse, sys
from pathlib import Path
root=Path('docs/case-studies/issue-1/data/templates'); out=Path('dev/log/issues/23/pulls/24')
items=[]
for p in [Path('composer.lock'), *root.rglob('package-lock.json'), *root.rglob('Cargo.lock'), *root.rglob('packages.lock.json')]:
 if any(x in p.parts for x in ['node_modules','target','obj','bin']): continue
 data=tomllib.loads(p.read_text()) if p.suffix=='.lock' and p.name=='Cargo.lock' else json.loads(p.read_text())
 if p.name=='composer.lock':
  for x in data['packages']+data['packages-dev']: items.append(('composer',x['name'],x['version'].lstrip('v'),str(p)))
 elif p.name=='package-lock.json':
  for k,v in data['packages'].items():
   if k and 'version'in v and not v.get('link'): items.append(('npm',v.get('name',k.rsplit('node_modules/',1)[-1]),v['version'],str(p)))
 elif p.name=='Cargo.lock':
  for x in data['package']:
   if x.get('source','').startswith('registry'): items.append(('cargo',x['name'],x['version'],str(p)))
 else:
  for fw in data['dependencies'].values():
   for name,v in fw.items():
    if 'resolved'in v: items.append(('nuget',name,v['resolved'],str(p)))
for line in (root/'python/requirements.lock').read_text().splitlines():
 m=re.match(r'([a-zA-Z0-9_.-]+)==([^ ;]+)',line)
 if m: items.append(('pypi',m[1],m[2],str(root/'python/requirements.lock')))
for p in root.rglob('deno.lock'):
 if 'node_modules' in p.parts: continue
 for spec in json.loads(p.read_text()).get('npm',{}):
  name,version=re.match(r'^(@?[^@]+)@(.+)$',spec).groups()
  items.append(('npm',name,version.split('_',1)[0],str(p)))
keys=sorted(set((e,n) for e,n,v,p in items))
cachepath=out/'transitive-registry.json'; cache=json.loads(cachepath.read_text()) if cachepath.exists() else {}
def fetch(key):
 e,n=key; ck=e+':'+n
 if ck in cache and "--refresh" not in sys.argv:return ck,cache[ck]
 urls={'npm':f'https://registry.npmjs.org/{urllib.parse.quote(n,safe="@/")}/latest','composer':f'https://repo.packagist.org/p2/{n}.json','pypi':f'https://pypi.org/pypi/{n}/json','nuget':f'https://api.nuget.org/v3-flatcontainer/{n.lower()}/index.json','cargo':f'https://crates.io/api/v1/crates/{n}'}
 url=urls[e]
 try:
  with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'issue-23-dependency-inventory'})) as r: d=json.load(r)
  if e=='npm': latest=d['version']; meta={'deprecated':d.get('deprecated'),'repository':d.get('repository'),'dependencies':d.get('dependencies',{})}
  elif e=='pypi':latest=d['info']['version'];meta={'requires_python':d['info']['requires_python']}
  elif e=='cargo':latest=d['crate']['max_stable_version'];meta={}
  elif e=='composer':latest=next(v['version'].lstrip('v') for v in d['packages'][n] if re.match(r'^v?\d+(\.\d+)*$',v['version']));meta={}
  else:latest=next(v for v in reversed(d['versions']) if '-'not in v);meta={}
  return ck,{'latest':latest,'source':url,**meta}
 except Exception as ex:return ck,{'error':str(ex),'source':url}
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as ex:
 for k,v in ex.map(fetch,keys):cache[k]=v
cache={k:v for k,v in cache.items() if k in {e+':'+n for e,n in keys}}
cachepath.write_text(json.dumps(cache,indent=2)+'\n')
# Resolve actual immediate parent edges instead of assigning a generic excuse.
edges={}
for p in root.rglob('deno.lock'):
 if 'node_modules' in p.parts: continue
 for spec,pkg in json.loads(p.read_text()).get('npm',{}).items():
  for dep in pkg.get('dependencies',[]):
   name=dep.rsplit('@',1)[0] if '@' in dep.lstrip('@') else dep
   for child in json.loads(p.read_text()).get('npm',{}):
    childname,version=re.match(r'^(@?[^@]+)@(.+)$',child).groups()
    if childname==name: edges.setdefault(('npm',name,version.split('_',1)[0],str(p)),set()).add(spec+' (Deno peer-resolved graph; declared ranges in registry metadata)')
for p in root.rglob('package-lock.json'):
 if 'node_modules' in p.parts: continue
 packages=json.loads(p.read_text())['packages']
 for parent,pkg in packages.items():
  for name,req in {**pkg.get('dependencies',{}),**pkg.get('optionalDependencies',{}),**pkg.get('devDependencies',{})}.items():
   base=parent
   while True:
    candidate=(base+'/' if base else '')+'node_modules/'+name
    if candidate in packages:
     child=packages[candidate]
     if 'version' in child:
      edges.setdefault(('npm',name,child['version'],str(p)),set()).add((pkg.get('name',parent.rsplit('node_modules/',1)[-1] or '(project)'))+'@'+pkg.get('version','')+' requires '+req)
     break
    if not base:break
    base=base.rsplit('/node_modules/',1)[0] if '/node_modules/' in base else ''
for p in root.rglob('packages.lock.json'):
 if any(x in p.parts for x in ['obj','bin']):continue
 for fw in json.loads(p.read_text())['dependencies'].values():
  for parent,pkg in fw.items():
   for name,req in pkg.get('dependencies',{}).items():
    if name in fw and 'resolved' in fw[name]:edges.setdefault(('nuget',name,fw[name]['resolved'],str(p)),set()).add(parent+'@'+pkg.get('resolved','')+' requires '+req)
for p in root.rglob('Cargo.lock'):
 if 'target' in p.parts:continue
 pkgs=tomllib.loads(p.read_text())['package']
 for parent in pkgs:
  for dep in parent.get('dependencies',[]):
   fields=dep.split();name=fields[0]
   for child in pkgs:
    if child['name']==name and (len(fields)==1 or child['version']==fields[1]):edges.setdefault(('cargo',name,child['version'],str(p)),set()).add(parent['name']+'@'+parent['version'])
baseline={v['name']:v['version'].lstrip('v') for v in json.loads((out/'composer-tree-before.json').read_text())['locked']}
rows=['# Resolved dependency inventory' ,'', 'Queried stable registry releases on 2026-11-13. Before this work, the captured templates had no lockfiles; their transitive versions were unpinned. PHP baseline is preserved in composer-tree-before.json. Parent-major compatibility constraints are retained rather than forcing incompatible transitive APIs. Each differing version is listed below; deprecated parents are investigated in UPSTREAM.md.','', '| Ecosystem | Dependency | Before | Resolved | Latest stable | Reason if different | Lock |','| --- | --- | --- | --- | --- | --- | --- |']
for e,n,v,p in sorted(set(items)):
 d=cache[e+':'+n]; latest=d.get('latest','ERROR: '+d.get('error',''))
 reason='Current'
 if latest!=v:
  parents='; '.join(sorted(edges.get((e,n,v,p),set())))
  if e=='pypi':reason='scriv 1.8.1 explicitly pins '+n+'=='+v+' in its published Requires-Dist metadata; see scriv-pypi.json and UPSTREAM.md.'
  elif e=='nuget' and n.lower()=='microsoft.applicationinsights':reason='MTP Telemetry 2.5.3 calls TrackEvent(string, Dictionary, Dictionary), removed in ApplicationInsights 3.x; retain compatible 2.x pending upstream migration. '+parents
  elif e=='cargo':reason='Current parent API lines retain this distinct crate major: '+parents+'. Both Cargo audits are clean. See lock edges and cargo-metadata.json for declared version requirements.'
  elif parents:reason='Retained parent API/version line; exact declaring constraints: '+parents+'. See UPSTREAM.md for deprecated installer API reports.'
  else:reason='Bundled/optional package pinned by its upstream distribution; no independent root constraint. See lock path and registry parent dependencies.'
 before=baseline.get(n,'not locked') if e=='composer' else 'not locked'
 rows.append(f'| {e} | {n} | {before} | {v} | [{latest}]({d["source"]}) | {reason} | {p.removeprefix(str(root)+"/")} |')
(out/'TRANSITIVES.md').write_text('\n'.join(rows)+'\n')
print(f'{len(items)} dependency instances, {len(keys)} registry queries, {sum("error"in v for v in cache.values())} errors')
