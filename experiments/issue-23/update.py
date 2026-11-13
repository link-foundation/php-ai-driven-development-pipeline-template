"""Apply registry-resolved direct versions consistently to every captured template."""
import json
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parents[2]
BASE = ROOT / 'docs/case-studies/issue-1/data/templates'
records = json.loads((ROOT / 'dev/log/issues/23/pulls/24/registry.json').read_text())['dependencies']
versions = {(x['ecosystem'], x['name']): x['latest'] for x in records}

def latest(eco, name): return versions[eco, name]

for path in list((ROOT / '.github').rglob('*.yml')) + list(BASE.rglob('*.yml')):
    text = path.read_text()
    def action(match):
        name = match[1]
        repo = '/'.join(name.split('/')[:2])
        return f'uses: {name}@{latest("github", repo)}'
    text = re.sub(r'uses:\s+([\w.-]+/[\w./-]+)@([^\s#]+)', action, text)
    if path.is_relative_to(BASE):
        text = text.replace('ubuntu-latest', 'ubuntu-24.04').replace('windows-latest', 'windows-2025').replace('macos-latest', 'macos-15')
        text = text.replace("node-version: '24.x'", "node-version: '26.13.1'").replace("node-version: '20.x'", "node-version: '26.13.1'")
        text = text.replace("python-version: '3.13'", "python-version: '3.15.0'").replace('python-version: "3.13"', "python-version: '3.15.0'")
        text = text.replace("dotnet-version: '8.0.x'", "dotnet-version: '11.0.100'")
        text = text.replace('cargo install rust-script', 'cargo install rust-script --version 0.36.0 --locked')
        text = text.replace('uses: taiki-e/install-action@v2.90.3\n', 'uses: taiki-e/install-action@v2.90.3\n        with:\n          tool: cargo-llvm-cov@0.9.1\n')
        text = text.replace('run: npm install\n', 'run: npm ci\n')
        text = text.replace('dotnet tool update -g docfx', 'dotnet tool restore')
        # Install the exact development/docs/publishing tree, including pip itself.
        text = text.replace('python -m pip install --upgrade pip', f'python -m pip install --upgrade pip=={latest("pypi", "pip")}')
        text = text.replace('pip install build twine "scriv[toml]"', 'pip install -r requirements.lock').replace('pip install build twine', 'pip install -r requirements.lock').replace('pip install "scriv[toml]"', 'pip install -r requirements.lock')
        text = text.replace('pip install -e ".[dev]"', 'pip install -r requirements.lock\n          pip install -e . --no-deps').replace('pip install -r docs/requirements.txt', 'pip install -r requirements.lock')
    path.write_text(text)

path = BASE / 'js/package.json'
data = json.loads(path.read_text())
for name in data['devDependencies']: data['devDependencies'][name] = '^' + latest('npm', name)
data['devDependencies']['@eslint/js'] = '^' + latest('npm', '@eslint/js')
data['engines']['node'] = '>=26.13.1'
data['packageManager'] = 'npm@11.20.0'
# Husky detects CI itself; do not silently swallow install failures.
data['scripts']['prepare'] = 'husky'
path.write_text(json.dumps(data, indent=2) + '\n')

path = BASE / 'python/pyproject.toml'
text = path.read_text().replace('requires = ["hatchling"]', f'requires = ["hatchling>={latest("pypi", "hatchling")}"]')
text = text.replace('license = {text = "Unlicense"}', 'license = "Unlicense"').replace('requires-python = ">=3.9"', 'requires-python = ">=3.15"')
text = re.sub(r'    "(?:License ::|Programming Language :: Python :: 3\.)[^\n]*\n', '', text)
text = text.replace('    "Programming Language :: Python :: 3",', '    "Programming Language :: Python :: 3",\n    "Programming Language :: Python :: 3.15",')
for record in records:
    if record['ecosystem'] == 'pypi':
        text = re.sub(r'("' + re.escape(record['name']) + r'(?:\[[^]]+\])?)>=[^"\n]+', lambda m: m[1] + '>=' + record['latest'], text)
text = text.replace('target-version = "py39"', 'target-version = "py315"').replace('python_version = "3.9"', 'python_version = "3.15"').replace('strict_concatenate = true', 'extra_checks = true').replace('minversion = "7.0"', 'minversion = "9.2"')
# Publishing and audit tools are resolved in the same lock, rather than ad hoc CI installs.
text = text.replace('    "scriv[toml]>=1.8.1",', '    "scriv[toml]>=1.8.1",\n' + ''.join(f'    "{n}>={latest("pypi", n)}",\n' for n in ['build','twine','pip-audit']) + '    "hatchling>=1.33.0",')
path.write_text(text)
path = BASE / 'python/docs/requirements.txt'
path.write_text(''.join(f'{n}=={latest("pypi", n)}\n' for n in ['sphinx','furo','myst-parser']))
for path in BASE.rglob('.pre-commit-config.yaml'):
    text = path.read_text()
    text = re.sub(r'(repo: https://github.com/(\S+)\s+rev: )\S+', lambda m: m[1] + ('v' + latest('pypi','mypy') if m[2] == 'pre-commit/mirrors-mypy' else latest('github',m[2])), text)
    text = text.replace('id: ruff\n', 'id: ruff-check\n').replace('additional_dependencies: [pytest, pytest-asyncio]', 'additional_dependencies: [pytest==9.2.0, pytest-asyncio==1.5.0]')
    path.write_text(text)
for path in list((BASE / 'csharp').rglob('*.csproj')) + [BASE / 'csharp/Directory.Build.props']:
    text = path.read_text().replace('net8.0','net11.0')
    text = re.sub(r'PackageReference Include="([^"]+)" Version="([^"]+)"', lambda m: f'PackageReference Include="{"xunit.v3" if m[1] == "xunit" else m[1]}" Version="{latest("nuget", "xunit.v3" if m[1] == "xunit" else m[1])}"', text)
    if path.name == 'MyPackage.Tests.csproj': text = text.replace('<IsTestProject>true</IsTestProject>', '<IsTestProject>true</IsTestProject>\n    <OutputType>Exe</OutputType>')
    if path.name == 'Directory.Build.props': text = text.replace('<Nullable>enable</Nullable>', '<Nullable>enable</Nullable>\n    <RestorePackagesWithLockFile>true</RestorePackagesWithLockFile>')
    path.write_text(text)
(BASE / 'csharp/global.json').write_text(json.dumps({'sdk':{'version':'11.0.100','rollForward':'disable'}},indent=2)+'\n')
(BASE / 'csharp/.config').mkdir(exist_ok=True)
(BASE / 'csharp/.config/dotnet-tools.json').write_text(json.dumps({'version':1,'isRoot':True,'tools':{'docfx':{'version':latest('nuget','docfx'),'commands':['docfx']}}},indent=2)+'\n')
path = BASE / 'rust/Cargo.toml'
text = path.read_text().replace('edition = "2021"','edition = "2024"').replace('rust-version = "1.70"','rust-version = "1.100"')
for name in ['lino-arguments','clap','regex','serde_json','walkdir']:
    text = re.sub(r'(?m)^(' + re.escape(name) + r' = (?:\{ version = )?)"[^"]+"', lambda m: m[1] + '"' + latest('cargo', name) + '"', text)
path.write_text(text)
(BASE / 'rust/rust-toolchain.toml').write_text('[toolchain]\nchannel = "1.100.0"\nprofile = "minimal"\ncomponents = ["clippy", "rustfmt"]\n')
for path in (BASE / 'rust').rglob('*.rs'):
    text = path.read_text()
    for name in ['regex','serde','serde_json','chrono','ureq','walkdir']:
        text = re.sub(r'(?m)^(//! ' + re.escape(name) + r' = (?:\{ version = )?)"[^"]+"', lambda m: m[1] + '"' + latest('cargo', name) + '"', text)
    path.write_text(text)
