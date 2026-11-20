<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\ChangeDetector;
use LinkFoundation\Template\Pipeline\Process;
use LinkFoundation\Template\Pipeline\ProcessResult;
use PHPUnit\Framework\TestCase;

final class ChangeDetectorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/change-detector-' . bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function testCliClassifiesCompletePushAndPrRanges(): void
    {
        $this->git('init', '-q');
        $base = $this->commit('README.md');
        $code = $this->commit("new\nsource.php");
        $docs = $this->commit('guide.md');

        foreach (['pull_request', 'push'] as $event) {
            $this->assertClassification($event, $base, $docs, true);
            $this->assertClassification($event, $code, $docs, false);
            $this->assertClassification($event, $docs, $docs, false);

            $result = $this->detect($event, str_repeat('a', 40), $docs);
            self::assertNotSame(0, $result->exitCode);
            self::assertStringContainsString('git diff', $result->stderr);
            self::assertSame('', file_get_contents($this->root . '/outputs'));
        }
    }

    public function testCliRejectsUnknownHistoryAndHandlesANewRef(): void
    {
        $zero = str_repeat('0', 40);
        $missing = str_repeat('a', 40);
        foreach (['push' => $zero, 'pull_request' => $missing, '' => ''] as $event => $base) {
            self::assertNotSame(0, $this->detect($event, $base, $missing)->exitCode);
            self::assertSame('', file_get_contents($this->root . '/outputs'));
        }

        $this->git('init', '-q');
        $initial = $this->commit('Initial.php');
        $this->assertClassification('push', $zero, $initial, true);
        $this->assertClassification('', '', '', true);
        $docs = $this->commit('guide.md');

        $this->git('clone', '-q', '--depth=1', 'file://' . $this->root, 'shallow');
        foreach (['pull_request', ''] as $event) {
            self::assertNotSame(0, $this->detect($event, $initial, $docs, $this->root . '/shallow')->exitCode);
            self::assertSame('', file_get_contents($this->root . '/outputs'));
        }
    }

    private function assertClassification(string $event, string $base, string $head, bool $codeChanged): void
    {
        $result = $this->detect($event, $base, $head);
        self::assertSame(0, $result->exitCode, $result->stderr);
        $flag = $codeChanged ? 'true' : 'false';
        self::assertStringContainsString("any-code-changed={$flag}\n", (string) file_get_contents($this->root . '/outputs'));
    }

    private function detect(string $event, string $base, string $head, ?string $cwd = null): ProcessResult
    {
        $output = $this->root . '/outputs';
        file_put_contents($output, '');

        return Process::run([
            'env',
            "GITHUB_EVENT_NAME={$event}",
            "GITHUB_BASE_SHA={$base}",
            "GITHUB_HEAD_SHA={$head}",
            "GITHUB_OUTPUT={$output}",
            \PHP_BINARY,
            \dirname(__DIR__, 2) . '/scripts/detect-code-changes.php',
        ], $cwd ?? $this->root);
    }

    private function commit(string $path): string
    {
        file_put_contents($this->root . '/' . $path, "content\n");
        $this->git('add', '--', $path);
        $this->git('-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '-q', '-m', 'Fixture');

        return $this->git('rev-parse', 'HEAD');
    }

    private function git(string ...$arguments): string
    {
        return Process::mustRun(['git', ...array_values($arguments)], $this->root)->output();
    }

    public function testClassifiesPhpSourceAsCode(): void
    {
        $flags = ChangeDetector::classify(['src/Calculator.php']);

        self::assertTrue($flags['php-changed']);
        self::assertTrue($flags['any-code-changed']);
        self::assertFalse($flags['docs-changed']);
    }

    public function testDocsOnlyChangeIsNotCode(): void
    {
        $flags = ChangeDetector::classify(['docs/index.md', 'README.md']);

        self::assertTrue($flags['docs-changed']);
        self::assertFalse($flags['any-code-changed']);
        self::assertFalse($flags['php-changed']);
    }

    public function testChangelogFragmentIsNotCode(): void
    {
        $flags = ChangeDetector::classify(['changelog.d/20260101_x.md']);

        self::assertFalse($flags['any-code-changed']);
    }

    public function testWorkflowChangeCountsAsCode(): void
    {
        $flags = ChangeDetector::classify(['.github/workflows/release.yml']);

        self::assertTrue($flags['workflow-changed']);
        self::assertTrue($flags['any-code-changed']);
    }

    public function testComposerManifestIsPackageChange(): void
    {
        $flags = ChangeDetector::classify(['composer.json']);

        self::assertTrue($flags['package-changed']);
        self::assertTrue($flags['any-code-changed']);
    }

    public function testTestsDirectoryFlag(): void
    {
        $flags = ChangeDetector::classify(['tests/Unit/CalculatorTest.php']);

        self::assertTrue($flags['tests-changed']);
        self::assertTrue($flags['php-changed']);
    }
}
