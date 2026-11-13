#!/usr/bin/env node

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gte, minVersion } from 'semver';

const metadata = JSON.parse(
  readFileSync(new URL('../package.json', import.meta.url), 'utf8')
);
export const NPM_MIN_VERSION = metadata.packageManager.slice('npm@'.length);
export const NODE_MIN_VERSION = minVersion(metadata.engines.node).version;
export const isSupportedNodeVersion = (version) =>
  gte(version, NODE_MIN_VERSION);
export const isSupportedNpmVersion = (version) => gte(version, NPM_MIN_VERSION);

/** Install the package manager pinned by this project, including OIDC support.
 * Node 26 ships a working npm, so the old Node 22 tarball/corepack fallbacks
 * are no longer needed. Keep version validation so an ineffective install fails.
 */
export async function setupNpm($) {
  if (!isSupportedNodeVersion(process.version)) {
    throw new Error(`Node.js ${NODE_MIN_VERSION} or later is required`);
  }
  await $`npm install --global npm@${NPM_MIN_VERSION}`;
  const result = await $`npm --version`.run({ capture: true });
  if (!isSupportedNpmVersion(result.stdout.trim())) {
    throw new Error(`npm ${NPM_MIN_VERSION} or later is required`);
  }
}

if (
  process.argv[1] &&
  resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
  const { $ } = await import('command-stream');
  await setupNpm($);
}
