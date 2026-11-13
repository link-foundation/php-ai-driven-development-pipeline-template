from pathlib import Path
import re
r=Path('docs/case-studies/issue-1/data/templates/js')
s=r/'scripts'
(s/'create-manual-changeset.mjs').write_text('''#!/usr/bin/env node
// Changesets 3 provides noninteractive creation, package quoting and formatting.
import { $ } from 'command-stream';
import { getJsRoot } from './js-paths.mjs';
import { readPackageInfo } from './package-info.mjs';
import { scriptOptions } from './script-options.mjs';
import { failScript } from './script-errors.mjs';

try {
  const { bumpType, description, jsRoot: configuredRoot } = scriptOptions('bump-type', 'description', 'js-root');
  if (!bumpType) throw new Error('Provide --bump-type major, minor, or patch');
  const cwd = getJsRoot({ jsRoot: configuredRoot, verbose: true });
  const { name } = readPackageInfo({ jsRoot: cwd });
  await $({ cwd })`npx --no-install changeset add ${'--' + bumpType} ${name} --message ${description || `Manual ${bumpType} release`}`;
} catch (error) {
  failScript('Error creating changeset', error);
}
''')
(s/'release-context.mjs').write_text('''import { getChangesetDir, getJsRoot, parseJsRootConfig } from './js-paths.mjs';
import { readPackageInfo } from './package-info.mjs';

export function releaseContext() {
  const jsRoot = getJsRoot({ jsRoot: parseJsRootConfig(), verbose: true });
  const changesetDir = getChangesetDir({ jsRoot });
  const { name: packageName } = readPackageInfo({ jsRoot });
  return { jsRoot, changesetDir, packageName };
}
''')
for name in ['merge-changesets','validate-changeset']:
 p=s/f'{name}.mjs';v=p.read_text();start=v.index('  const jsRootConfig = parseJsRootConfig();');end=v.index('\n  console.log(`Package:',start)
 v=v[:start]+'  const { changesetDir, packageName } = releaseContext();'+v[end:]
 v=v.replace('\n',"\nimport { releaseContext } from './release-context.mjs';\n",1);p.write_text(v)
# One workflow parser shared by policy tests; normalize CRLF at the boundary.
(r/'tests/workflow-helpers.js').write_text('''import { readFileSync } from 'node:fs';

export function readWorkflow(filePath) {
  return readFileSync(filePath, 'utf8').replaceAll('\\r\\n', '\\n');
}

export function getJobBlock(workflow, jobName) {
  const lines = workflow.replaceAll('\\r\\n', '\\n').split('\\n');
  const start = lines.indexOf(`  ${jobName}:`);
  if (start === -1) return '';
  const end = lines.findIndex((line, index) => index > start && /^ {2}[a-zA-Z0-9_-]+:\\s*$/.test(line));
  return lines.slice(start, end === -1 ? lines.length : end).join('\\n');
}
''')
for name in ['ci-timeouts','workflow-reliability']:
 p=r/f'tests/{name}.test.js';v=p.read_text()
 for func in ['readWorkflow','getJobBlock']:
  v=re.sub(r'function '+func+r'\([^\n]*\) \{.*?^\}\n','',v,flags=re.M|re.S)
 v="import { readWorkflow, getJobBlock } from './workflow-helpers.js';\n"+v;p.write_text(v)
# Run fixture lifecycle once; assertions remain per scenario.
p=r/'tests/check-file-line-limits.test.js';v=p.read_text();mark=v.index("describe('check-file-line-limits.sh'")
v=v[:mark]+'''function checkFixture(files, verify) {
  const root = createFixture(files);
  try {
    verify(runLineLimitCheck(root));
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
}

'''+v[mark:]
pattern=r'      const root = createFixture\((\{.*?\})\);\n\n      try \{\n        const result = runLineLimitCheck\(root\);\n(.*?)      \} finally \{\n        rmSync\(root, \{ recursive: true, force: true \}\);\n      \}'
v,n=re.subn(pattern,lambda m:'      checkFixture('+m[1]+', (result) => {\n'+m[2]+'      });',v,flags=re.S)
p.write_text(v);print('fixture refactors',n)
# Compact long repeated fs import lists through a namespace.
for name in ['check-file-line-limits','create-github-release']:
 p=r/f'tests/{name}.test.js';v=p.read_text();m=re.search(r"import \{([^}]+)\} from 'node:fs';",v)
 if m:
  names=[x.strip()for x in m[1].split(',')if x.strip()];v=v[:m.start()]+"import * as fs from 'node:fs';"+v[m.end():]
  for name in names:v=re.sub(r'(?<![.\w])'+re.escape(name)+r'\(', 'fs.'+name+'(',v)
 p.write_text(v)
p=r/'.prettierignore';v=p.read_text();p.write_text(v+'\nreports/\n')
