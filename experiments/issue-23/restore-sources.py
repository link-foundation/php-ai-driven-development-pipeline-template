"""Restore source/test files omitted from the original captured sibling templates.

Use the source revision at the original case study commit, never today's source.
Do not overwrite captured files; verify the original manifest blob matches first.
"""
import concurrent.futures
import json
import pathlib
import subprocess
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / 'dev/log/issues/23/pulls/24'
BASE = ROOT / 'docs/case-studies/issue-1/data/templates'

def gh(path): return json.loads(subprocess.check_output(['gh','api',path,'--paginate']))

restored=[]
for lang, manifest in [('js','package.json'),('rust','Cargo.toml'),('csharp','src/MyPackage/MyPackage.csproj')]:
    repo=f'link-foundation/{lang}-ai-driven-development-pipeline-template'
    revision=gh(f'repos/{repo}/commits?until=2026-06-09T18:40:14Z&per_page=100')[0]['sha']
    tree=gh(f'repos/{repo}/git/trees/{revision}?recursive=1')['tree']
    original=subprocess.check_output(['git','show',f'99eedcd:docs/case-studies/issue-1/data/templates/{lang}/{manifest}'])
    original_sha=subprocess.check_output(['git','hash-object','--stdin'],input=original).decode().strip()
    match=next(x['sha'] for x in tree if x['path']==manifest)==original_sha
    if not match:
        # The capture may precede another same-day upstream commit. Find the matching manifest revision.
        for commit in gh(f'repos/{repo}/commits?path={manifest}&until=2026-06-09T18:40:14Z&per_page=10000'):
            candidate=gh(f'repos/{repo}/git/trees/{commit["sha"]}?recursive=1')['tree']
            if next(x['sha'] for x in candidate if x['path']==manifest)==original_sha:
                revision=commit['sha']; tree=candidate; match=True; break
    if not match: raise RuntimeError(f'Cannot identify original {lang} manifest revision')
    (OUT/f'{lang}-source-tree.json').write_text(json.dumps({'repository':repo,'revision':revision,'original_manifest_verified':True,'tree':tree},indent=2)+'\n')
    paths=[x['path'] for x in tree if x['type']=='blob' and (x['path'].startswith(('src/','tests/','scripts/')) or x['path'] in ['LICENSE','CHANGELOG.md']) and not (BASE/lang/x['path']).exists()]
    def download(path):
        url=f'https://raw.githubusercontent.com/{repo}/{revision}/{path}'
        with urllib.request.urlopen(url) as response: content=response.read()
        target=BASE/lang/path; target.parent.mkdir(parents=True,exist_ok=True); target.write_bytes(content)
        return {'path':str(target.relative_to(ROOT)),'source':url}
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        restored.extend(pool.map(download, paths))
    print(lang,revision,'restored',len(paths),flush=True)
(OUT/'restored-sources.json').write_text(json.dumps(restored,indent=2)+'\n')
