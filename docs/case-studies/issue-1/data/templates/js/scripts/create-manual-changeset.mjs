#!/usr/bin/env node
// Changesets 3 provides noninteractive creation, package quoting and formatting.
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { getJsRoot } from './js-paths.mjs';
import { readPackageInfo } from './package-info.mjs';
import { scriptOptions } from './script-options.mjs';
import { failScript } from './script-errors.mjs';

try {
  const {
    bumpType,
    description,
    jsRoot: configuredRoot,
  } = scriptOptions('bump-type', 'description', 'js-root');
  if (!['major', 'minor', 'patch'].includes(bumpType)) {
    throw new Error('Provide --bump-type major, minor, or patch');
  }
  const cwd = getJsRoot({ jsRoot: configuredRoot, verbose: true });
  const { name } = readPackageInfo({ jsRoot: cwd });
  execFileSync(
    process.execPath,
    [
      fileURLToPath(import.meta.resolve('@changesets/cli/bin.js')),
      'add',
      `--${bumpType}`,
      name,
      '--message',
      description || `Manual ${bumpType} release`,
    ],
    { cwd, stdio: 'inherit' }
  );
} catch (error) {
  failScript('Error creating changeset', error);
}
