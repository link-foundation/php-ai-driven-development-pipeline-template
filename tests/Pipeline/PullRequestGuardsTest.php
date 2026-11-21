<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\Process;
use LinkFoundation\Template\Pipeline\ProcessResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class PullRequestGuardsTest extends TestCase
{
    private const MANIFEST = '{"name":"fixture/package","version":"1.2.3"}';
    private const NO_VERSION = '{"name":"fixture/package"}';
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/pr-guards-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/scripts/src', 0o777, true);
        $source = \dirname(__DIR__, 2) . '/scripts';
        foreach (['bootstrap.php', 'check-version-modification.php', 'validate-changeset.php'] as $file) {
            copy("{$source}/{$file}", "{$this->root}/scripts/{$file}");
        }
        foreach (glob($source . '/src/*.php') ?: [] as $file) {
            copy($file, $this->root . '/scripts/src/' . basename($file));
        }

        $this->git('init', '-q', '--initial-branch=main');
        $this->git('config', 'commit.gpgsign', 'false');
        $this->git('config', 'core.hooksPath', $this->root . '/no-hooks');
        $this->git('remote', 'add', 'origin', $this->root);
        file_put_contents($this->root . '/README.md', "Local fixture\n");
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

    /** @return iterable<string, array{?string, ?string, int, string}> */
    public static function versionComparisons(): iterable
    {
        yield 'unchanged, different formatting' => [self::MANIFEST, '{"version": "1.2.3"}', 0, 'unchanged'];
        yield 'absent on both sides' => [self::NO_VERSION, self::NO_VERSION, 0, 'unchanged'];
        yield 'changed' => [self::MANIFEST, '{"version":"9.9.9"}', 1, 'changes the "version"'];
        yield 'version added' => [self::NO_VERSION, self::MANIFEST, 1, 'changes the "version"'];
        yield 'version removed' => [self::MANIFEST, self::NO_VERSION, 1, 'changes the "version"'];
        yield 'new manifest' => [null, self::MANIFEST, 0, 'new file'];
        yield 'missing head' => [self::MANIFEST, null, 1, 'read head composer.json'];
        yield 'invalid first manifest' => [null, '{invalid', 1, 'head composer.json'];

        foreach ([
            'invalid JSON' => '{invalid',
            'array' => '[]',
            'null document' => 'null',
            'null version' => '{"version":null}',
            'numeric version' => '{"version":123}',
            'boolean version' => '{"version":false}',
            'object version' => '{"version":{}}',
        ] as $name => $invalid) {
            yield "base {$name}" => [$invalid, self::MANIFEST, 1, 'base composer.json'];
            yield "head {$name}" => [self::MANIFEST, $invalid, 1, 'head composer.json'];
        }
    }

    #[DataProvider('versionComparisons')]
    public function testComparesOnlyValidManifestEvidence(?string $base, ?string $head, int $status, string $message): void
    {
        $this->writeHeadManifest($base);
        $this->commit();
        $this->writeHeadManifest($head);

        $this->assertGuard($status, $message, $this->runGuard('check-version-modification'));
    }

    public function testCannotReadBaseManifestFromADirectoryEntry(): void
    {
        mkdir($this->root . '/composer.json');
        file_put_contents($this->root . '/composer.json/file', 'fixture');
        $this->commit();
        unlink($this->root . '/composer.json/file');
        rmdir($this->root . '/composer.json');
        $this->writeHeadManifest(self::MANIFEST);

        $this->assertGuard(1, 'git cat-file blob', $this->runGuard('check-version-modification'));
    }

    public function testFailedFetchCannotUseMissingOrStaleTrackingRef(): void
    {
        $this->writeHeadManifest(self::MANIFEST);
        $this->commit();
        $this->git('remote', 'set-url', 'origin', $this->root . '/missing-origin');

        $this->assertGuard(1, 'git fetch', $this->runGuard('check-version-modification'));
        $this->git('update-ref', 'refs/remotes/origin/main', 'HEAD');
        $this->assertGuard(1, 'git fetch', $this->runGuard('check-version-modification'));
    }

    #[TestWith(['main'])]
    #[TestWith(['0'])]
    public function testComparesTheFetchedCommitEvenWithoutATrackingRef(string $branch): void
    {
        $this->writeHeadManifest(self::MANIFEST);
        $this->commit();
        $this->git('branch', '0');
        $this->git('config', 'remote.origin.fetch', '+refs/heads/unrelated:refs/remotes/origin/unrelated');
        $fetchHeadPath = $this->root . '/.git/FETCH_HEAD';
        file_put_contents($fetchHeadPath, "Another fetch's state\n");
        $this->writeHeadManifest('{"version":"9.9.9"}');

        $result = $this->runGuard('check-version-modification', ['GITHUB_BASE_REF' => $branch]);
        $this->assertGuard(1, 'changes the "version"', $result);
        self::assertSame("Another fetch's state\n", file_get_contents($fetchHeadPath));
        self::assertSame('', $this->git('for-each-ref', 'refs/php-pipeline/'));
    }

    public function testVersionGuardRequiresABaseBranchForPullRequestsOnly(): void
    {
        $env = ['GITHUB_BASE_REF' => ''];
        $this->assertGuard(1, 'GITHUB_BASE_REF', $this->runGuard('check-version-modification', $env));
        $this->assertGuard(0, 'skipping version-modification check', $this->runGuard(
            'check-version-modification',
            $env + ['GITHUB_EVENT_NAME' => 'push'],
        ));
    }

    public function testChangesetGuardRequiresVerifiedPrEvidenceAndAnAddedFragment(): void
    {
        $this->writeHeadManifest(self::MANIFEST);
        mkdir($this->root . '/changelog.d');
        $this->writeFragment('existing.md');
        $this->commit();
        $base = $this->git('rev-parse', 'HEAD');
        $this->git('checkout', '-q', '-b', 'feature');
        $this->writeFragment('new.md');
        $this->commit();
        $head = $this->git('rev-parse', 'HEAD');

        $this->assertGuard(0, 'validation passed', $this->runGuard('validate-changeset'));
        $this->assertGuard(0, 'validation passed', $this->runGuard('validate-changeset', [
            'GITHUB_BASE_SHA' => $base,
            'GITHUB_HEAD_SHA' => $head,
        ]));
        $this->assertGuard(1, 'No changelog fragment found', $this->runGuard('validate-changeset', [
            'GITHUB_BASE_SHA' => $base,
            'GITHUB_HEAD_SHA' => $base,
        ]));
        $this->assertGuard(1, 'git diff', $this->runGuard('validate-changeset', [
            'GITHUB_BASE_SHA' => str_repeat('a', 40),
            'GITHUB_HEAD_SHA' => $head,
        ]));

        $this->git('remote', 'set-url', 'origin', $this->root . '/missing-origin');
        $this->assertGuard(1, 'git fetch', $this->runGuard('validate-changeset'));
        $this->assertGuard(0, 'validation passed', $this->runGuard('validate-changeset', [
            'GITHUB_EVENT_NAME' => '',
            'GITHUB_BASE_REF' => '',
        ]));
    }

    private function writeHeadManifest(?string $manifest): void
    {
        $path = $this->root . '/composer.json';
        if ($manifest !== null) {
            file_put_contents($path, $manifest);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }

    private function writeFragment(string $file): void
    {
        file_put_contents($this->root . '/changelog.d/' . $file, "---\nbump: patch\n---\n### Fixed\n- A fix.\n");
    }

    private function commit(): void
    {
        $this->git('add', '-A', '--', '.', ':!scripts');
        $this->git('-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '-q', '-m', 'Fixture');
    }

    private function git(string ...$arguments): string
    {
        return Process::mustRun(['git', ...array_values($arguments)], $this->root)->output();
    }

    /** @param array<string, string> $environment */
    private function runGuard(string $script, array $environment = []): ProcessResult
    {
        $command = ['env', 'GIT_TERMINAL_PROMPT=0'];
        foreach ($environment + [
            'GITHUB_EVENT_NAME' => 'pull_request',
            'GITHUB_BASE_REF' => 'main',
            'GITHUB_BASE_SHA' => '',
            'GITHUB_HEAD_SHA' => '',
        ] as $key => $value) {
            $command[] = "{$key}={$value}";
        }

        return Process::run([...$command, PHP_BINARY, 'scripts/' . $script . '.php'], $this->root);
    }

    private function assertGuard(int $status, string $message, ProcessResult $result): void
    {
        $log = $result->stdout . $result->stderr;
        self::assertSame($status, $result->exitCode, $log);
        self::assertStringContainsString($message, $log);
    }
}
