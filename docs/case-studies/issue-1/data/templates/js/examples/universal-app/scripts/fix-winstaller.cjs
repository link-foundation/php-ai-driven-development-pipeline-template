// Published 5.4.0 predates electron/windows-installer#614's os.arch() fix.
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
