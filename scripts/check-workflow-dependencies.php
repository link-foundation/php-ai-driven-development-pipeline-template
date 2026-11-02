<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use LinkFoundation\Template\Pipeline\Actions;
use LinkFoundation\Template\Pipeline\WorkflowDependencies;

try {
    $workflows = [];
    foreach (glob(__DIR__ . '/../.github/workflows/*.{yml,yaml}', GLOB_BRACE) ?: [] as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException('Cannot read ' . $file);
        }
        $workflows['.github/workflows/' . basename($file)] = $content;
    }
    if ($workflows === []) {
        throw new RuntimeException('No active workflows found');
    }
    $findings = (new WorkflowDependencies())->findings($workflows);
    // A newer upstream release is external state: gate it on pull requests,
    // where a contributor can act, and warn on push, schedule and manual runs
    // so main does not turn red without a commit (issue #20). Unpinned runner
    // images and lookup failures still fail on every event.
    $exitCode = WorkflowDependencies::report($findings, getenv('GITHUB_EVENT_NAME') === 'pull_request');
    if ($findings !== []) {
        exit($exitCode);
    }
    echo "All hosted runner images are explicit and workflow dependencies use current stable releases.\n";
} catch (Throwable $error) {
    Actions::error($error->getMessage());
    exit(1);
}
