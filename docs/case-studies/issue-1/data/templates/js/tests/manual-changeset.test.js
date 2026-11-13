import { describe, it, expect } from 'test-anywhere';
import { execFileSync } from 'node:child_process';
import {
  mkdtempSync,
  mkdirSync,
  writeFileSync,
  readdirSync,
  readFileSync,
  rmSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

describe('Changesets 3 noninteractive release creation', () => {
  it('creates a scoped package changeset in a separate root containing spaces', () => {
    const root = mkdtempSync(join(tmpdir(), 'changeset root '));
    try {
      writeFileSync(
        join(root, 'package.json'),
        JSON.stringify({ name: '@example/scoped', version: '1.0.0' })
      );
      mkdirSync(join(root, '.changeset'));
      writeFileSync(
        join(root, '.changeset/config.json'),
        JSON.stringify({
          changelog: false,
          commit: false,
          baseBranch: 'main',
          access: 'public',
        })
      );
      execFileSync(
        'node',
        [
          fileURLToPath(
            new URL('../scripts/create-manual-changeset.mjs', import.meta.url)
          ),
          '--js-root',
          root,
          '--bump-type',
          'minor',
          '--description',
          'Quoted "message"\nwith a second line',
        ],
        { encoding: 'utf8' }
      );
      const file = readdirSync(join(root, '.changeset')).find((name) =>
        name.endsWith('.md')
      );
      const content = readFileSync(join(root, '.changeset', file), 'utf8');
      expect(content).toContain('"@example/scoped": minor');
      expect(content).toContain('Quoted "message"\nwith a second line');
    } finally {
      rmSync(root, { recursive: true, force: true });
    }
  });
});
