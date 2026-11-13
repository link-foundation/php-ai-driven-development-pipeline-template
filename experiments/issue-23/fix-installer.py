from pathlib import Path
import json
r=Path('docs/case-studies/issue-1/data/templates/js')
# Avoid a duplicated six-line import preamble by using the existing URL global.
p=r/'tests/check-file-line-limits.test.js';s=p.read_text().replace("import { fileURLToPath, URL } from 'node:url';", "import { fileURLToPath } from 'node:url';");p.write_text(s)
a=r/'examples/universal-app';(a/'scripts').mkdir(exist_ok=True)
(a/'scripts/fix-winstaller.cjs').write_text('''// Published 5.4.0 predates electron/windows-installer#614's os.arch() fix.
// Keep this narrow backport until that upstream fix is released.
const { readFileSync, writeFileSync } = require('node:fs');
const { dirname, join } = require('node:path');
const { execFileSync } = require('node:child_process');

function fixArchitectureSelection(source) {
  return source.replace('const arch = os.arch;', 'const arch = os.arch();');
}

module.exports = { fixArchitectureSelection };
if (require.main === module) {
  const root = dirname(require.resolve('electron-winstaller/package.json'));
  const script = join(root, 'script/select-7z-arch.js');
  const source = readFileSync(script, 'utf8');
  const fixed = fixArchitectureSelection(source);
  if (fixed !== source) {
    writeFileSync(script, fixed);
  }
  execFileSync(process.execPath, [script], { cwd: root, stdio: 'inherit' });
}
''')
p=a/'package.json';d=json.loads(p.read_text());d['scripts']['postinstall']='node scripts/fix-winstaller.cjs';d['allowScripts']={'electron-winstaller':False};p.write_text(json.dumps(d,indent=2)+'\n')
(r/'tests/winstaller.test.js').write_text('''import { describe, it, expect } from 'test-anywhere';
import { createRequire } from 'node:module';
const { fixArchitectureSelection } = createRequire(import.meta.url)('../examples/universal-app/scripts/fix-winstaller.cjs');

describe('published Windows installer architecture selection', () => {
  it('calls os.arch and preserves already-fixed upstream scripts', () => {
    const broken = 'const arch = os.arch;';
    const fixed = fixArchitectureSelection(broken);
    const selected = new Function('os', `${fixed} return arch;`)({ arch: () => 'x64' });
    expect(selected).toBe('x64');
    expect(fixArchitectureSelection(fixed)).toBe(fixed);
  });
});
''')
