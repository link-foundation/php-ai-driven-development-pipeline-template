#!/usr/bin/env bun
import { failScript } from './script-errors.mjs';

/**
 * Custom changeset version script that ensures package-lock.json is synchronized
 * with package.json after version bumps.
 *
 * This script:
 * 1. Detects the JavaScript package root (supports both single-language and multi-language repos)
 * 2. Runs `changeset version` to update package versions
 * 3. Runs `npm install` to synchronize package-lock.json with the new versions
 *
 * Configuration:
 * - CLI: --js-root <path> to explicitly set JavaScript root
 * - Environment: JS_ROOT=<path>
 *
 * Uses link-foundation libraries:
 * - command-stream: Modern shell command execution with streaming support
 *
 * Addresses issues documented in:
 * - Issue #21: Supporting both single and multi-language repository structures
 * - Reference: link-assistant/agent PR #114 (configurable package root)
 */

import { getJsRoot, parseJsRootConfig } from './js-paths.mjs';

// Import command-stream for shell command execution
import { $ } from 'command-stream';

try {
  // Get JavaScript package root (auto-detect or use explicit config)
  const jsRootConfig = parseJsRootConfig();
  const jsRoot = getJsRoot({ jsRoot: jsRootConfig, verbose: true });

  console.log('Running changeset version...');

  await $({ cwd: jsRoot })`npx changeset version`;

  console.log('\nSynchronizing package-lock.json...');

  // Refresh the lockfile using npm’s peer dependency validation.
  await $({ cwd: jsRoot })`npm install --package-lock-only`;

  console.log('\n✅ Version bump complete with synchronized package-lock.json');
} catch (error) {
  failScript('Error during version bump', error);
}
