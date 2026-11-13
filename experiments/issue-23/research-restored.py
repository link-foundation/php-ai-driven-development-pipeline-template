"""Resolve dependencies discovered in restored source, keeping the original inventory."""
import concurrent.futures,json,pathlib,urllib.request
out=pathlib.Path('dev/log/issues/23/pulls/24');base=pathlib.Path('docs/case-studies/issue-1/data/templates/js')
p=json.loads((base/'examples/universal-app/package.json').read_text());items={k:v for g in ['dependencies','devDependencies'] for k,v in p[g].items()}
items.update({'command-stream':'unversioned use-m import','lino-arguments':'unversioned use-m import','semver':'migration candidate'})
def get(item):
 name,before=item;url=f'https://registry.npmjs.org/{name}/latest'
 with urllib.request.urlopen(url) as r: d=json.load(r)
 return {'ecosystem':'npm','name':name,'before':[before],'latest':d['version'],'source':url,'details':{k:d.get(k) for k in ['engines','repository','peerDependencies']}}
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:data=list(pool.map(get,items.items()))
(out/'registry-restored.json').write_text(json.dumps(data,indent=2)+'\n')
for r in data:print(r['name'],r['latest'])
