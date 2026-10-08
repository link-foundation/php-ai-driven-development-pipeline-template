<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

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
                    os: [ubuntu-24.04, 'macos-latest', windows-latest]
              block:
                strategy:
                  matrix:
                    os:
                      - "ubuntu-latest"
            # runs-on: ubuntu-latest is forbidden, but this comment is fine.
            YAML;

        self::assertCount(3, WorkflowDependencies::runnerErrors($yaml));
        self::assertSame([], WorkflowDependencies::runnerErrors('runs-on: ubuntu-24.04'));
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
}
