<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests that encode the hard-won CI/CD policies from the sibling
 * templates as assertions over the workflow YAML, so a careless edit that
 * re-introduces a known footgun fails locally before it reaches CI.
 */
final class WorkflowPolicyTest extends TestCase
{
    private static function workflow(string $name): string
    {
        $path = \dirname(__DIR__, 2) . '/.github/workflows/' . $name;
        $contents = file_get_contents($path);
        self::assertIsString($contents, "Missing workflow: {$name}");

        return $contents;
    }

    public function testReleaseWorkflowExists(): void
    {
        self::assertStringContainsString('name: CI/CD Pipeline', self::workflow('release.yml'));
    }

    public function testConcurrencyNeverCancelsMain(): void
    {
        $yaml = self::workflow('release.yml');

        self::assertStringContainsString('concurrency:', $yaml);
        self::assertStringContainsString(
            "cancel-in-progress: \${{ github.ref != 'refs/heads/main' }}",
            $yaml,
            'Runs on main must never be cancelled mid-release.',
        );
    }

    public function testGatesUseNotCancelledNotAlwaysAlone(): void
    {
        $yaml = self::workflow('release.yml');

        // The release gates rely on !cancelled() so a skipped detect-changes
        // dependency does not silently skip lint/test/release.
        self::assertStringContainsString('!cancelled()', $yaml);
    }

    public function testEveryJobHasATimeout(): void
    {
        foreach (['release.yml', 'docs.yml', 'links.yml'] as $file) {
            $yaml = self::workflow($file);
            // One `runs-on:` per job; every job must carry a timeout.
            $jobCount = substr_count($yaml, 'runs-on:');
            $timeoutCount = substr_count($yaml, 'timeout-minutes:');

            self::assertGreaterThan(0, $jobCount, "{$file} should declare jobs.");
            self::assertSame(
                $jobCount,
                $timeoutCount,
                "{$file}: every job should declare a timeout-minutes.",
            );
        }
    }

    public function testReleaseJobsRequireWriteContents(): void
    {
        $yaml = self::workflow('release.yml');

        self::assertStringContainsString('contents: write', $yaml);
    }

    public function testCiCdLogicIsInvokedThroughPhpScripts(): void
    {
        $yaml = self::workflow('release.yml');

        foreach ([
            'scripts/detect-code-changes.php',
            'scripts/check-release-needed.php',
            'scripts/version-and-commit.php',
            'scripts/create-github-release.php',
        ] as $script) {
            self::assertStringContainsString($script, $yaml, "Workflow should call {$script}.");
        }
    }

    public function testNoForeignRuntimesInWorkflows(): void
    {
        // The pipeline must be native PHP: no node/python/ruby steps.
        foreach (['release.yml', 'docs.yml', 'links.yml'] as $file) {
            $yaml = self::workflow($file);

            self::assertStringNotContainsString('setup-node', $yaml, "{$file} must not set up Node.");
            self::assertStringNotContainsString('setup-python', $yaml, "{$file} must not set up Python.");
            self::assertDoesNotMatchRegularExpression('/run:\s*(node|python|ruby|npm|pip) /', $yaml);
        }
    }
}
