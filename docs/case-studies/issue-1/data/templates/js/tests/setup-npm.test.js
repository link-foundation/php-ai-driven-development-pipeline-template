import { describe, it, expect } from 'test-anywhere';
import assert from 'node:assert/strict';
import {
  NODE_MIN_VERSION,
  NPM_MIN_VERSION,
  isSupportedNodeVersion,
  isSupportedNpmVersion,
  setupNpm,
} from '../scripts/setup-npm.mjs';

function mockCommand(version) {
  const calls = [];
  const command = (strings, ...values) => {
    calls.push(String.raw({ raw: strings }, ...values));
    return { run: async () => ({ stdout: version }) };
  };
  return { command, calls };
}

describe('setup-npm version requirements', () => {
  it('requires the project package manager and runtime floors', () => {
    expect(isSupportedNpmVersion('11.20.0')).toBe(false);
    expect(isSupportedNpmVersion(NPM_MIN_VERSION)).toBe(true);
    expect(isSupportedNodeVersion('v26.13.0')).toBe(false);
    expect(isSupportedNodeVersion(NODE_MIN_VERSION)).toBe(true);
  });

  it('installs the pinned npm and verifies the resulting executable', async () => {
    const { command, calls } = mockCommand(NPM_MIN_VERSION);
    await setupNpm(command);
    expect(calls).toEqual([
      `npm install --global npm@${NPM_MIN_VERSION}`,
      'npm --version',
    ]);
  });

  it('fails if installation leaves an older executable on PATH', async () => {
    const { command } = mockCommand('11.20.0');
    await assert.rejects(setupNpm(command), /npm .* or later is required/);
  });
});
