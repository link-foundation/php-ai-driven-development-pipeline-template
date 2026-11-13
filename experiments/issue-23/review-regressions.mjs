import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const root = resolve('docs/case-studies/issue-1/data/templates/js');
const require = createRequire(`${root}/package.json`);
const { parseDocument } = require('yaml');
const workflow = '.github/workflows/release.yml';
const before = spawnSync('git', ['show', `a6d7de8:${root.slice(process.cwd().length + 1)}/${workflow}`], { encoding: 'utf8' });
assert.equal(before.status, 0, before.stderr);
const broken = parseDocument(before.stdout);
assert.ok(broken.errors.length + broken.warnings.length > 0);
console.log('Before: parser rejects unquoted !cancelled() release conditions.');
const fixed = parseDocument(readFileSync(`${root}/${workflow}`, 'utf8'));
assert.deepEqual(fixed.errors, []);
assert.deepEqual(fixed.warnings, []);
console.log('After: all workflow conditions parse without errors or warnings.');

const result = spawnSync(process.execPath, [`${root}/node_modules/eslint/bin/eslint.js`, '--stdin', '--stdin-filename', 'src/spacing-probe.js'], {
  cwd: root,
  input: '//missing-space\nexport const value = 1;\n',
  encoding: 'utf8',
});
assert.equal(result.status, 1, result.stderr);
assert.match(result.stdout, /@stylistic\/spaced-comment/);
console.log('Preserved lint policy: missing comment spacing fails through @stylistic/spaced-comment.');
