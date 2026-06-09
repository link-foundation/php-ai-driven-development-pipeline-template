<?php

declare(strict_types=1);

/**
 * Guard against manual version bumps in a pull request.
 *
 * The version in composer.json is owned by the release pipeline (driven by
 * changelog fragments), never by hand. If a PR edits the `version` field we
 * fail fast with a clear explanation, mirroring the "do not edit the version
 * manually" rule from the sibling templates.
 *
 * Release commits are made by the bot on the default branch, so this check only
 * runs for pull requests.
 */

require_once __DIR__ . '/bootstrap.php';

use LinkFoundation\Template\Pipeline\Actions;
use LinkFoundation\Template\Pipeline\Process;
use LinkFoundation\Template\Pipeline\Project;

$project = Project::locate();
$root = $project->root();

$baseRef = getenv('GITHUB_BASE_REF') ?: '';

if ($baseRef === '') {
    echo "Not a pull request (no GITHUB_BASE_REF); skipping version-modification check.\n";
    exit(0);
}

// Make sure the base branch is available, then diff only composer.json.
Process::run(['git', 'fetch', '--no-tags', '--depth=1', 'origin', $baseRef], $root);

$diff = Process::run(
    ['git', 'diff', "origin/{$baseRef}...HEAD", '--unified=0', '--', 'composer.json'],
    $root,
);

if (!$diff->ok()) {
    Actions::warning('Could not diff composer.json against the base branch; skipping check.');
    exit(0);
}

$touchesVersion = false;

foreach (explode("\n", $diff->output()) as $line) {
    // Added or removed lines (not the diff header "+++"/"---").
    if (preg_match('/^[+-](?![+-])/', $line) && preg_match('/"version"\s*:/', $line)) {
        $touchesVersion = true;

        break;
    }
}

if ($touchesVersion) {
    Actions::error(
        'This pull request modifies the "version" field in composer.json. '
        . 'Versions are managed automatically by the release pipeline from '
        . 'changelog fragments — add one with `composer changeset` instead of '
        . 'editing the version by hand.',
    );
    exit(1);
}

echo "composer.json version field is unchanged in this PR.\n";
