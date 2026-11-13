#!/usr/bin/env bun
import { failScript } from './script-errors.mjs';

/**
 * Format GitHub release notes using the format-release-notes.mjs script
 * Usage: node scripts/format-github-release.mjs --release-version <version> --repository <repository> --commit-sha <commit_sha> [--tag-prefix <prefix>]
 *   release-version: Version number (e.g., 1.0.0)
 *   repository: GitHub repository (e.g., owner/repo)
 *   commit_sha: Commit SHA for PR detection
 *   tag-prefix: Prefix for the git tag (default: "v", use "js-v" for multi-language repos)
 *
 * Uses link-foundation libraries:
 * - command-stream: Modern shell command execution with streaming support
 * - lino-arguments: Unified configuration from CLI args, env vars, and .lenv files
 */

// Import link-foundation libraries
import { $ } from 'command-stream';
import { scriptOptions } from './script-options.mjs';

// Parse CLI arguments using lino-arguments
// Note: Using --release-version instead of --version to avoid conflict with yargs' built-in --version flag
const config = scriptOptions(
  'release-version',
  'repository',
  'commit-sha',
  'tag-prefix'
);

const { releaseVersion: version, repository, commitSha, tagPrefix } = config;

if (!version || !repository || !commitSha) {
  console.error('Error: Missing required arguments');
  console.error(
    'Usage: node scripts/format-github-release.mjs --release-version <version> --repository <repository> --commit-sha <commit_sha> [--tag-prefix <prefix>]'
  );
  process.exit(1);
}

const tag = `${tagPrefix}${version}`;

try {
  // Get the release ID for this version
  let releaseId = '';
  try {
    const result =
      await $`gh api "repos/${repository}/releases/tags/${tag}" --jq '.id'`.run(
        { capture: true }
      );
    releaseId = result.stdout.trim();
  } catch {
    console.log(`\u26A0\uFE0F Could not find release for ${tag}`);
    process.exit(0);
  }

  if (releaseId) {
    console.log(`Formatting release notes for ${tag}...`);
    // Pass the trigger commit SHA for PR detection
    // This allows proper PR lookup even if the changelog doesn't have a commit hash
    await $`node scripts/format-release-notes.mjs --release-id "${releaseId}" --release-version "${tag}" --repository "${repository}" --commit-sha "${commitSha}"`;
    console.log(`\u2705 Formatted release notes for ${tag}`);
  }
} catch (error) {
  failScript('Error formatting release', error);
}
