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
use LinkFoundation\Template\Pipeline\Project;
use LinkFoundation\Template\Pipeline\VersionGuard;

$baseRef = getenv('GITHUB_BASE_REF');

try {
    if ($baseRef === false || $baseRef === '') {
        if (getenv('GITHUB_EVENT_NAME') === 'pull_request') {
            throw new RuntimeException('Missing GITHUB_BASE_REF for the pull request version check.');
        }

        echo "No base branch provided; skipping version-modification check.\n";
        exit(0);
    }

    echo VersionGuard::check(new Project(dirname(__DIR__)), $baseRef) . "\n";
} catch (RuntimeException $error) {
    Actions::error($error->getMessage());
    exit(1);
}
