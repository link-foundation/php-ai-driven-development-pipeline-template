import json, pathlib, subprocess, urllib.request, concurrent.futures
out=pathlib.Path('dev/log/issues/23/pulls/24')
items=[('npm',n) for n in ['npm','secretlint','@secretlint/secretlint-rule-preset-recommend','browser-commander','playwright','globals']]+[('github',n) for n in ['rhysd/actionlint','oven-sh/bun','denoland/deno','astral-sh/uv','python/cpython','phpDocumentor/phpDocumentor']]
def one(item):
 eco,name=item
 if eco=='npm':
  url=f'https://registry.npmjs.org/{name}/latest'
  with urllib.request.urlopen(url) as r: data=json.load(r)
  return {'ecosystem':eco,'name':name,'latest':data['version'],'before':['workflow tool or migration helper'],'source':url,'details':{k:data.get(k) for k in ['engines','peerDependencies','repository']}}
 data=json.loads(subprocess.check_output(['gh','api',f'repos/{name}/releases?per_page=100','--paginate']))
 stable=[r for r in data if not r['draft'] and not r['prerelease']]
 if not stable:
  tags=json.loads(subprocess.check_output(['gh','api',f'repos/{name}/tags?per_page=100','--paginate']))
  import re
  latest=max((t['name'] for t in tags if re.fullmatch(r'v?\d+\.\d+\.\d+',t['name'])),key=lambda s:tuple(map(int,s.lstrip('v').split('.'))))
  return {'ecosystem':eco,'name':name,'before':['toolchain'],'latest':latest,'source':f'https://github.com/{name}/releases/tag/{latest}','details':{}}
 r=stable[0]
 return {'ecosystem':eco,'name':name,'before':['toolchain / workflow tool'],'latest':r['tag_name'],'source':r['html_url'],'details':{'body':r['body']}}
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
 records=list(pool.map(one,items))
(out/'registry-extra.json').write_text(json.dumps(records,indent=2)+'\n')
for r in records: print(r['ecosystem'],r['name'],r['latest'])
