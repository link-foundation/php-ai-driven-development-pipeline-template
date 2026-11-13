from pathlib import Path
import re
r=Path('docs/case-studies/issue-1/data/templates/js')
# Remove redundant parsing now handled by lino-arguments' environment mapping.
for name in ['instant-version-bump','publish-to-npm']:
 p=r/f'scripts/{name}.mjs';s=p.read_text();s=s.replace('  const jsRootConfig = jsRootArg || parseJsRootConfig();\n  const jsRoot = getJsRoot({ jsRoot: jsRootConfig, verbose: true });','  const jsRoot = getJsRoot({ jsRoot: jsRootArg, verbose: true });').replace('const jsRootConfig = jsRootArg || parseJsRootConfig();\nconst jsRoot = getJsRoot({ jsRoot: jsRootConfig, verbose: true });','const jsRoot = getJsRoot({ jsRoot: jsRootArg, verbose: true });').replace(', parseJsRootConfig','').replace('  parseJsRootConfig,\n','');p.write_text(s)
for name in ['merge-changesets','validate-changeset']:
 p=r/f'scripts/{name}.mjs';s=p.read_text();s=re.sub(r"import \{ getChangesetDir, getJsRoot, parseJsRootConfig \} from './js-paths.mjs';\n",'',s);s=s.replace('  readPackageInfo,\n','');p.write_text(s)
p=r/'scripts/create-manual-changeset.mjs';s=p.read_text().replace("  if (!bumpType) throw new Error('Provide --bump-type major, minor, or patch');", "  if (!['major', 'minor', 'patch'].includes(bumpType)) {\n    throw new Error('Provide --bump-type major, minor, or patch');\n  }").replace("${'--' + bumpType}", '${`--${bumpType}`}');p.write_text(s)
p=r/'tests/workflow-helpers.js';s=p.read_text().replace("  if (start === -1) return '';", "  if (start === -1) {\n    return '';\n  }");p.write_text(s)
p=r/'tests/workflow-reliability.test.js';s=p.read_text().replace("import { readFileSync } from 'node:fs';", "import { readFileSync } from 'node:fs';\nimport { parse } from 'yaml';").replace("    const packageVersion = previewRegenJob.match(/playwright@([0-9.]+)/)?.[1];", "    const packageVersion = JSON.parse(readFileSync('package.json', 'utf8')).devDependencies.playwright.slice(1);")
s=s.replace("    expect(desktopPackageJob).toContain(\"node-version: '26.13.1'\");", "    const setupNode = parse(exampleAppWorkflow).jobs['desktop-package'].steps.find((step) => step.uses?.startsWith('actions/setup-node@'));\n    expect(setupNode.with['node-version']).toBe('26.13.1');")
p.write_text(s)
# YAML anchors reuse steps and conditions without changing individual jobs.
for p in (r/'.github/workflows').glob('*.yml'):
 s=p.read_text();counter=0;seen={}
 pattern=r'^      - (?![&*])[^\n]*\n(?:(?: {8}[^\n]*|)\n)*'
 def repl(m):
  global counter
  block=m[0].rstrip();lines=block.splitlines()
  if block in seen:return f'      - *{seen[block]}\n\n'
  if s.count(block)<2:return m[0]
  counter+=1;name=f'common-step-{counter}';seen[block]=name
  return f'      - &{name}\n        '+lines[0][8:]+'\n'+'\n'.join(lines[1:])+'\n\n'
 s=re.sub(pattern,repl,s,flags=re.M)
 # Anchor each distinct multi-line job condition.
 conditions={}
 def cond(m):
  value=m[0]
  if value in conditions:return f'    if: *{conditions[value]}\n'
  if s.count(value)<2:return value
  name=f'condition-{len(conditions)+1}';conditions[value]=name
  return value.replace('if: |', f'if: &{name} |',1)
 s=re.sub(r'^    if: \|\n(?:      .+\n)+',cond,s,flags=re.M)
 s=s.replace('PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD:', 'PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD:')
 s=re.sub(r'          npm install --no-save[^\n]*browser-commander[^\n]*\n', '          npm ci\n', s)
 p.write_text(s)
# One fixture lifecycle shared across command integration tests.
(r/'tests/fixtures.js').write_text("import { rmSync } from 'node:fs';\n\nexport function withFixture(create, verify) {\n  const root = create();\n  try {\n    return verify(root);\n  } finally {\n    rmSync(root, { recursive: true, force: true });\n  }\n}\n")
for name in ['create-github-release.test.js','check-file-line-limits.test.js']:
 p=r/'tests'/name;s=p.read_text();s="import { withFixture } from './fixtures.js';\n"+s
 pat=r'(?P<ind> *)const root = createFixture\(\);\n\n(?P=ind)try \{(?P<body>.*?)\n(?P=ind)\} finally \{\n(?P=ind)  fs.rmSync\(root, \{ recursive: true, force: true \}\);\n(?P=ind)\}'
 s=re.sub(pat,lambda m:m['ind']+'withFixture(createFixture, (root) => {'+m['body']+'\n'+m['ind']+'});',s,flags=re.S)
 if name.startswith('check-file'):
  s=s.replace("import path from 'node:path';", "import { join, dirname, delimiter } from 'node:path';").replace('path.join','join').replace('path.dirname','dirname').replace('path.delimiter','delimiter')
  start=s.index("    it('still fails files over the hard limit'")
  end=s.index("    it('exempts case-study",start)
  s=s[:start]+'''    for (const [label, files] of [
      ['still fails files over the hard limit', { 'src/too-large.mjs': 1501 }],
      ['fails .js and .cjs files over the hard limit', { 'src/too-large.js': 1501, 'src/legacy.cjs': 1600 }],
      ['fails Markdown files over the hard limit', { 'docs/HUGE.md': 1501 }],
    ]) {
      it(label, () => {
        checkFixture(files, (result) => {
          expect(result.status).toBe(1);
          for (const [file, count] of Object.entries(files)) {
            expect(result.stdout).toContain(`ERROR: ./${file} has ${count} lines (limit: 1500)`);
          }
          expect(result.stdout).toContain('The following files exceed the 1500 line limit:');
          expect(result.stdout).toContain(`  ./${Object.keys(files)[0]}`);
        });
      });
    }

'''+s[end:]
 else:
  start=s.index('    expect(parseArgs([], {}))')
  end=s.index('\n  });',start)
  s=s[:start]+'''    for (const [args, env, language] of [
      [[], {}, 'JavaScript'],
      [[], { LANGUAGE: 'TypeScript' }, 'TypeScript'],
      [['--language', 'JavaScript'], {}, 'JavaScript'],
      [['--language=Rust'], {}, 'Rust'],
    ]) {
      expect(parseArgs(args, env)).toEqual({language, releaseVersion: '', repository: '', tagPrefix: 'v'});
    }'''+s[end:]
  start=s.index("    it('creates a release") if "    it('creates a release" in s else -1
  # Consolidate successful payload cases while retaining separate named tests.
  start=s.index("    it('passes a human-readable release name for language-prefixed tags'")
  end=s.index("    it('fails when gh api",start)
  block=s[start:end]
  block=block.replace("body: '### Patch Changes\\n\\n- Fix release creation',", "body: ['### Patch Changes', '- Fix release creation'].join('\\n\\n'),")
  s=s[:start]+block+s[end:]
 p.write_text(s)
