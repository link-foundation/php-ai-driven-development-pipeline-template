"""Collect small, dated registry records for every direct dependency and action."""
import concurrent.futures
import datetime
import json
import pathlib
import re
import subprocess
import tomllib
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / 'dev/log/issues/23/pulls/24'
BASE = ROOT / 'docs/case-studies/issue-1/data/templates'

def fetch(url):
    with urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'issue-23-dependency-research'})) as response:
        return json.load(response)

def gh(path):
    return json.loads(subprocess.check_output(['gh', 'api', path, '--paginate'], text=True))

def resolve(item):
    ecosystem, name, before = item
    if ecosystem == 'npm':
        url = f'https://registry.npmjs.org/{name}/latest'
        data = fetch(url)
        latest = data['version']
        details = {k: data.get(k) for k in ['engines', 'peerDependencies', 'deprecated', 'repository']}
    elif ecosystem == 'pypi':
        url = f'https://pypi.org/pypi/{name}/json'
        info = fetch(url)['info']
        latest = info['version']
        details = {k: info.get(k) for k in ['requires_python', 'project_urls', 'requires_dist']}
    elif ecosystem == 'composer':
        url = f'https://repo.packagist.org/p2/{name}.json'
        data = fetch(url)['packages'][name]
        stable = [x for x in data if re.fullmatch(r'v?\d+\.\d+\.\d+', x['version'])]
        info = stable[0]
        latest = info['version'].lstrip('v')
        details = {k: info.get(k) for k in ['require', 'source', 'time']}
    elif ecosystem == 'cargo':
        url = f'https://crates.io/api/v1/crates/{name}'
        data = fetch(url)
        latest = data['crate']['max_stable_version']
        info = next(x for x in data['versions'] if x['num'] == latest)
        details = {k: info.get(k) for k in ['rust_version', 'created_at', 'features']}
    elif ecosystem == 'nuget':
        url = f'https://api.nuget.org/v3-flatcontainer/{name.lower()}/index.json'
        versions = fetch(url)['versions']
        latest = [v for v in versions if '-' not in v][-1]
        details = {}
    else:
        url = f'https://api.github.com/repos/{name}/releases'
        releases = gh(f'repos/{name}/releases?per_page=100')
        stable = [x for x in releases if not x['prerelease'] and not x['draft'] and re.fullmatch(r'v?\d+\.\d+(?:\.\d+)?', x['tag_name'])]
        if stable:
            info = max(stable, key=lambda x: tuple(map(int, x['tag_name'].lstrip('v').split('.'))))
            latest = info['tag_name']
            details = {k: info.get(k) for k in ['html_url', 'published_at', 'body']}
        else:
            commits = gh(f'repos/{name}/commits?per_page=1')
            latest = commits[0]['sha']
            details = {'reason': 'upstream publishes no stable semantic release; pin commit'}
    return {'ecosystem': ecosystem, 'name': name, 'before': sorted(before), 'latest': latest, 'source': url, 'details': details}

items = {}
def add(eco, name, version):
    items.setdefault((eco, name), set()).add(version)

composer = json.loads((ROOT / 'composer.json').read_text())
for name, version in composer['require-dev'].items(): add('composer', name, version)
package = json.loads((BASE / 'js/package.json').read_text())
for name, version in package['devDependencies'].items(): add('npm', name, version)
add('npm', '@eslint/js', 'implicit (imported but undeclared)')
project = tomllib.loads((BASE / 'python/pyproject.toml').read_text())
for dep in project['build-system']['requires'] + sum(project['project']['optional-dependencies'].values(), []):
    match = re.match(r'([\w-]+)(?:\[[^]]+\])?(.*)', dep)
    add('pypi', match[1], match[2] or 'unbounded')
for name in ['build', 'twine', 'pip', 'pip-audit', 'uv', 'zizmor']:
    add('pypi', name, 'CI tool / unbounded' if name != 'zizmor' else '1.31.1')
for path in (BASE / 'csharp').rglob('*.csproj'):
    for name, version in re.findall(r'PackageReference Include="([^"]+)" Version="([^"]+)"', path.read_text()): add('nuget', name, version)
add('nuget', 'xunit.v3', 'migration candidate from xunit 2')
add('nuget', 'docfx', 'unbounded dotnet tool')
cargo = tomllib.loads((BASE / 'rust/Cargo.toml').read_text())
for group in ['dependencies', 'dev-dependencies']:
    for name, version in cargo[group].items(): add('cargo', name, version if isinstance(version, str) else version['version'])
for path in (BASE / 'rust').rglob('*.rs'):
    for block in re.findall(r'//! ```cargo\n(.*?)//! ```', path.read_text(), re.S):
        data = tomllib.loads(re.sub(r'(?m)^//! ?', '', block))
        for name, version in data.get('dependencies', {}).items(): add('cargo', name, version if isinstance(version, str) else version['version'])
for name in ['rust-script', 'cargo-audit', 'cargo-llvm-cov']:
    add('cargo', name, 'unbounded tool')
for path in list((ROOT / '.github').rglob('*.yml')) + list(BASE.rglob('*.yml')):
    for name, version in re.findall(r'uses:\s+([\w.-]+/[\w./-]+)@([^\s#]+)', path.read_text()):
        add('github', '/'.join(name.split('/')[:2]), version)
for path in BASE.rglob('.pre-commit-config.yaml'):
    for name, version in re.findall(r'repo: https://github.com/(\S+)\s+rev: (\S+)', path.read_text()): add('github', name, version)
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    records = []
    futures = {pool.submit(resolve, (eco, name, before)): (eco, name) for (eco, name), before in items.items()}
    for future in concurrent.futures.as_completed(futures):
        try: records.append(future.result())
        except Exception as error: records.append({'ecosystem': futures[future][0], 'name': futures[future][1], 'error': str(error)})
records.sort(key=lambda r: (r['ecosystem'], r['name']))
(OUT / 'registry.json').write_text(json.dumps({'collected_at': datetime.datetime.now(datetime.UTC).isoformat(), 'dependencies': records}, indent=2) + '\n')
lines = ['# Direct dependency registry inventory', '', 'Latest stable releases returned by the linked registries at collection time. Prereleases are excluded. Full metadata and release notes: registry.json.', '', '| Ecosystem | Dependency | Before | Latest stable | Source |', '| --- | --- | --- | --- | --- |']
for r in records:
    lines.append(f"| {r['ecosystem']} | {r['name']} | {', '.join(r.get('before', []))} | {r.get('latest', r.get('error'))} | [registry]({r.get('source', '')}) |")
(OUT / 'DEPENDENCIES.md').write_text('\n'.join(lines) + '\n')
print(f'Collected {len(records)} dependencies; errors: {[r for r in records if "error" in r]}')
