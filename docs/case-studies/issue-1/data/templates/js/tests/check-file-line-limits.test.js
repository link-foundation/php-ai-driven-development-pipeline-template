import { withFixture, createTempDir } from './fixtures.js';
import { describe, it, expect } from 'test-anywhere';
import { spawnSync } from 'node:child_process';
import * as fs from 'node:fs';
import { join, dirname, delimiter } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptPath = fileURLToPath(
  new URL('../scripts/check-file-line-limits.sh', import.meta.url)
);
const isDenoRuntime = typeof Deno !== 'undefined';
const canRunBashFixtures =
  !isDenoRuntime &&
  typeof process !== 'undefined' &&
  process.platform !== 'win32';

function lines(count) {
  return `${Array.from({ length: count }, (_, index) => `line-${index + 1}`).join('\n')}\n`;
}

function createFixture(files) {
  const root = createTempDir('line-limit-');

  for (const [filePath, lineCount] of Object.entries(files)) {
    const absolutePath = join(root, filePath);
    fs.mkdirSync(dirname(absolutePath), { recursive: true });
    fs.writeFileSync(absolutePath, lines(lineCount));
  }

  return root;
}

function runLineLimitCheck(root, env) {
  return spawnSync('bash', [scriptPath], {
    cwd: root,
    encoding: 'utf8',
    env,
  });
}

function checkFixture(files, verify) {
  withFixture(
    () => createFixture(files),
    (root) => {
      verify(runLineLimitCheck(root));
    }
  );
}

describe('check-file-line-limits.sh', () => {
  it('defines a warning band below the hard limit', () => {
    const script = fs.readFileSync(scriptPath, 'utf8');

    expect(script).toContain('LIMIT=1500');
    expect(script).toContain('WARN_THRESHOLD=1350');
    expect(script).toContain('WARNINGS=()');
    expect(script).toContain('::warning file=');
  });

  if (canRunBashFixtures) {
    it('warns without failing for files above the warning threshold', () => {
      checkFixture(
        {
          'src/near-limit.mjs': 1351,
          '.github/workflows/release.yml': 1351,
        },
        (result) => {
          expect(result.status).toBe(0);
          expect(result.stdout).toContain(
            'WARNING: ./src/near-limit.mjs has 1351 lines'
          );
          expect(result.stdout).toContain(
            'WARNING: .github/workflows/release.yml has 1351 lines'
          );
          expect(result.stdout).toContain(
            'The following files are approaching the 1500 line limit (>1350 lines):'
          );
          expect(result.stdout).toContain('  ./src/near-limit.mjs');
          expect(result.stdout).toContain('  .github/workflows/release.yml');
          expect(result.stdout).not.toContain(
            'The following files exceed the 1500 line limit:'
          );
        }
      );
    });

    for (const [label, files] of [
      ['still fails files over the hard limit', { 'src/too-large.mjs': 1501 }],
      [
        'fails .js and .cjs files over the hard limit',
        { 'src/too-large.js': 1501, 'src/legacy.cjs': 1600 },
      ],
      ['fails Markdown files over the hard limit', { 'docs/HUGE.md': 1501 }],
    ]) {
      it(label, () => {
        checkFixture(files, (result) => {
          expect(result.status).toBe(1);
          for (const [file, count] of Object.entries(files)) {
            expect(result.stdout).toContain(
              `ERROR: ./${file} has ${count} lines (limit: 1500)`
            );
          }
          expect(result.stdout).toContain(
            'The following files exceed the 1500 line limit:'
          );
          expect(result.stdout).toContain(`  ./${Object.keys(files)[0]}`);
        });
      });
    }

    it('exempts case-study generated-data files from the limit', () => {
      checkFixture(
        {
          'docs/case-studies/issue-99/data/raw.md': 5000,
          'docs/case-studies/issue-99/data/sample.cjs': 5000,
        },
        (result) => {
          expect(result.status).toBe(0);
          expect(result.stdout).not.toContain(
            'The following files exceed the 1500 line limit:'
          );
          expect(result.stdout).not.toContain('data/raw.md');
          expect(result.stdout).not.toContain('data/sample.cjs');
        }
      );
    });

    it('normalizes padded wc output from BSD-like environments', () => {
      const root = createFixture({
        'src/near-limit.mjs': 1351,
      });
      const binPath = join(root, 'bin');
      const fakeWcPath = join(binPath, 'wc');

      try {
        fs.mkdirSync(binPath, { recursive: true });
        fs.writeFileSync(
          fakeWcPath,
          `#!/bin/sh
awk 'END { printf "    %d\\n", NR }'
`
        );
        fs.chmodSync(fakeWcPath, 0o755);

        const result = runLineLimitCheck(root, {
          ...process.env,
          PATH: `${binPath}${delimiter}${process.env.PATH ?? ''}`,
        });

        expect(result.status).toBe(0);
        expect(result.stdout).toContain(
          'WARNING: ./src/near-limit.mjs has 1351 lines'
        );
        expect(result.stdout).not.toContain('has     1351 lines');
      } finally {
        fs.rmSync(root, { recursive: true, force: true });
      }
    });
  }
});
