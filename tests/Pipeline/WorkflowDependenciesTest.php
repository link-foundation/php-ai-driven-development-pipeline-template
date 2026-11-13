<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\Process;
use LinkFoundation\Template\Pipeline\ProcessResult;
use LinkFoundation\Template\Pipeline\WorkflowDependencies;
use PHPUnit\Framework\TestCase;

final class WorkflowDependenciesTest extends TestCase
{
    public function testHostedRunnerAliasesAreRejectedInScalarAndMatrixForms(): void
    {
        $yaml = <<<'YAML'
            jobs:
              direct:
                runs-on: ubuntu-latest
              matrix:
                runs-on: ${{ matrix.os }}
                strategy:
                  matrix:
                    os: [ubuntu-26.04, 'macos-latest', windows-latest]
              block:
                strategy:
                  matrix:
                    os:
                      - "ubuntu-latest"
            # runs-on: ubuntu-latest is forbidden, but this comment is fine.
            YAML;

        self::assertCount(3, WorkflowDependencies::runnerErrors($yaml));
        self::assertSame([], WorkflowDependencies::runnerErrors('runs-on: ubuntu-26.04'));
    }

    public function testScannerIncludesSubActionsAndPinnedToolVersions(): void
    {
        $yaml = <<<'YAML'
            steps:
              - uses: actions/checkout@v7.0.1
              - uses: 'github/codeql-action/analyze@v4.38.2'
              - uses: zizmorcore/zizmor-action@v0.6.4
                with:
                  version: 1.30.1
              - uses: docker://rhysd/actionlint@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v1.7.12
              - run: pipx run zizmor==1.30.1 .github
              - uses: ./local/action
            YAML;

        $dependencies = WorkflowDependencies::dependencies($yaml);
        self::assertSame([
            ['repository' => 'actions/checkout', 'reference' => 'v7.0.1', 'line' => 2],
            ['repository' => 'github/codeql-action', 'reference' => 'v4.38.2', 'line' => 3],
            ['repository' => 'zizmorcore/zizmor-action', 'reference' => 'v0.6.4', 'line' => 4],
            ['repository' => 'zizmorcore/zizmor', 'reference' => '1.30.1', 'line' => 6],
            ['repository' => 'rhysd/actionlint', 'reference' => 'v1.7.12', 'line' => 7],
            ['repository' => 'zizmorcore/zizmor', 'reference' => '1.30.1', 'line' => 8],
        ], $dependencies);
    }

    public function testStaleActionsAndToolInputsFailAndLookupsAreDeduplicated(): void
    {
        $calls = [];
        $checker = new WorkflowDependencies(static function (string $repo) use (&$calls): string {
            $calls[] = $repo;

            return $repo === 'zizmorcore/zizmor' ? 'v1.30.1' : 'v7.0.1';
        });
        $errors = $checker->check([
            'a.yml' => "uses: actions/checkout@v4\nuses: actions/checkout@v7.0.1\nuses: zizmorcore/zizmor-action@v7.0.1\n  version: 1.29.0",
            'b.yml' => 'uses: actions/checkout@v7.0.0',
        ]);

        self::assertCount(3, $errors);
        self::assertStringContainsString('a.yml:1', $errors[0]);
        self::assertStringContainsString('v7.0.1', $errors[0]);
        self::assertStringContainsString('zizmorcore/zizmor', $errors[1]);
        self::assertCount(3, $calls);
    }

    public function testReleaseSelectionIgnoresBundlesAndPrereleases(): void
    {
        self::assertSame('v4.38.2', WorkflowDependencies::newestRelease("codeql-bundle-v2.27.2\nv3.38.2\nv4.38.1\nv4.38.2\nv5.0.0-rc.1\n"));
    }

    public function testLookupFailuresFailClosed(): void
    {
        $calls = 0;
        $checker = new WorkflowDependencies(static function () use (&$calls): string {
            ++$calls;
            throw new \RuntimeException('API unavailable');
        });
        $errors = $checker->check(['test.yml' => "uses: actions/checkout@v7.0.1\nuses: actions/checkout@v7.0.1"]);

        self::assertCount(2, $errors);
        self::assertStringContainsString('API unavailable', $errors[0]);
        self::assertSame(1, $calls);
    }

    public function testOnlyNewerUpstreamReleasesAreMarkedAsOutdated(): void
    {
        $checker = new WorkflowDependencies(static fn (string $repo): string => $repo === 'broken/action'
            ? throw new \RuntimeException('API unavailable')
            : 'v7.0.1');
        $findings = $checker->findings([
            'a.yml' => "runs-on: ubuntu-latest\nuses: actions/checkout@v7.0.0\nuses: broken/action@v1.0.0\nuses: actions/checkout@v7.0.1",
        ]);

        self::assertSame([false, true, false], array_column($findings, 'outdated'));
        self::assertStringContainsString('ubuntu-26.04', $findings[0]['message']);
        self::assertStringContainsString('must use the latest stable release v7.0.1', $findings[1]['message']);
        self::assertStringContainsString('API unavailable', $findings[2]['message']);
    }

    public function testOutdatedPinsFailPullRequestsButOnlyWarnElsewhere(): void
    {
        $outdated = [['message' => 'a.yml:1: actions/checkout@v7.0.0 must use the latest stable release v7.0.1', 'outdated' => true]];
        $broken = [['message' => 'a.yml:2: cannot check broken/action: API unavailable', 'outdated' => false]];

        ob_start();
        self::assertSame(1, WorkflowDependencies::report($outdated, gateOutdated: true));
        self::assertStringStartsWith('::error::', (string) ob_get_clean());

        ob_start();
        self::assertSame(0, WorkflowDependencies::report($outdated, gateOutdated: false));
        $output = (string) ob_get_clean();
        self::assertStringStartsWith('::warning::', $output);
        self::assertStringNotContainsString('::error::', $output);

        ob_start();
        self::assertSame(1, WorkflowDependencies::report([...$outdated, ...$broken], gateOutdated: false));
        $output = (string) ob_get_clean();
        self::assertStringContainsString('::warning::a.yml:1', $output);
        self::assertStringContainsString('::error::a.yml:2', $output);
    }

    /**
     * End-to-end over the real workflows with a stub `gh` that reports a newer
     * release of everything: the script must exit 1 on pull requests and 0
     * with warnings and a step summary on push, schedule and manual runs.
     */
    public function testScriptGatesOutdatedPinsOnlyOnPullRequests(): void
    {
        $directory = self::stubGh("#!/bin/sh\necho v999.0.0\n");
        try {
            foreach (['pull_request' => 1, 'push' => 0, 'schedule' => 0, 'workflow_dispatch' => 0] as $event => $expected) {
                file_put_contents($directory . '/summary.md', '');
                $result = self::runScript($directory, $event);
                $output = $result->stdout . $result->stderr;

                self::assertSame($expected, $result->exitCode, "{$event}: {$output}");
                self::assertStringContainsString('must use the latest stable release v999.0.0', $output);
                if ($expected === 1) {
                    self::assertStringContainsString('::error::', $output);
                    self::assertStringNotContainsString('::warning::', $output);
                } else {
                    self::assertStringContainsString('::warning::', $output);
                    self::assertStringNotContainsString('::error::', $output);
                    self::assertStringContainsString('Outdated workflow dependencies', (string) file_get_contents($directory . '/summary.md'));
                }
            }
        } finally {
            self::removeDirectory($directory);
        }
    }

    public function testScriptStillFailsOnLookupFailuresOutsidePullRequests(): void
    {
        $directory = self::stubGh("#!/bin/sh\necho 'API unavailable' >&2\nexit 1\n");
        try {
            $result = self::runScript($directory, 'schedule');

            self::assertSame(1, $result->exitCode, $result->stdout);
            self::assertStringContainsString('::error::', $result->stdout);
        } finally {
            self::removeDirectory($directory);
        }
    }

    private static function stubGh(string $script): string
    {
        $directory = sys_get_temp_dir() . '/workflow-dependencies-' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/gh', $script);
        chmod($directory . '/gh', 0o755);

        return $directory;
    }

    private static function runScript(string $directory, string $event): ProcessResult
    {
        $root = \dirname(__DIR__, 2);

        return Process::run([
            'env',
            'PATH=' . $directory . ':' . (string) getenv('PATH'),
            'GITHUB_EVENT_NAME=' . $event,
            'GITHUB_STEP_SUMMARY=' . $directory . '/summary.md',
            PHP_BINARY, $root . '/scripts/check-workflow-dependencies.php',
        ], $root);
    }

    private static function removeDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}
