<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\Process;
use LinkFoundation\Template\Pipeline\Project;
use LinkFoundation\Template\Pipeline\VersionReleaser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests using a local bare Git remote.
 */
final class VersionReleaserTest extends TestCase
{
    private string $root;
    private string $remote;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/vr-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/changelog.d', 0o775, true);

        $composer = json_encode(['name' => 'vendor/pkg', 'version' => '1.0.0'], \JSON_PRETTY_PRINT);
        self::assertIsString($composer);
        file_put_contents($this->root . '/composer.json', $composer . "\n");
        file_put_contents(
            $this->root . '/CHANGELOG.md',
            "# Changelog\n\n<!-- changelog-insert-here -->\n",
        );

        $this->remote = $this->root . '/.git/remote.git';
        Process::mustRun(['git', 'init', '-q', '-b', 'main'], $this->root);
        Process::mustRun(['git', 'config', 'user.email', 'test@example.com'], $this->root);
        Process::mustRun(['git', 'config', 'user.name', 'Test'], $this->root);
        Process::mustRun(['git', 'add', '.'], $this->root);
        Process::mustRun(['git', 'commit', '-q', '-m', 'init'], $this->root);
        Process::mustRun(['git', 'init', '-q', '--bare', $this->remote], $this->root);
        Process::mustRun(['git', 'remote', 'add', 'origin', $this->remote], $this->root);
        Process::mustRun(['git', 'push', '-q', 'origin', 'main'], $this->root);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    public function testChangesetReleaseBumpsCommitsAndTags(): void
    {
        file_put_contents(
            $this->root . '/changelog.d/a.md',
            "---\nbump: minor\n---\n### Added\n- A new feature.",
        );

        $project = new Project($this->root);
        $result = VersionReleaser::forProject($project)->release('changeset', null, null, '2026-06-09');

        self::assertSame('1.1.0', $result['new_version']);
        self::assertTrue($result['version_committed']);
        self::assertFalse($result['already_released']);

        // composer.json was bumped.
        self::assertSame('1.1.0', $project->version());

        // CHANGELOG.md gained the entry.
        $changelog = file_get_contents($this->root . '/CHANGELOG.md');
        self::assertIsString($changelog);
        self::assertStringContainsString('## [1.1.0] - 2026-06-09', $changelog);
        self::assertStringContainsString('- A new feature.', $changelog);

        // Fragments were consumed.
        self::assertSame([], glob($this->root . '/changelog.d/*.md'));

        // Tag was created.
        $tags = Process::run(['git', 'tag'], $this->root)->output();
        self::assertStringContainsString('v1.1.0', $tags);
    }

    public function testInstantReleaseUsesExplicitBump(): void
    {
        $manifest = $this->root . '/composer.json';
        file_put_contents($manifest, '{"name":"vendor/pkg","version":"1.0.0","repositories":[{"packagist.org":false}]}');
        Process::mustRun(['composer', 'update', '--no-install', '--no-interaction', '--no-audit'], $this->root);
        Process::mustRun(['git', 'add', 'composer.json', 'composer.lock'], $this->root);
        Process::mustRun(['git', 'commit', '-qm', 'Lock fixture dependencies'], $this->root);
        $project = new Project($this->root);
        $result = VersionReleaser::forProject($project)
            ->release('instant', 'patch', 'Hotfix the thing.', '2026-06-09');

        self::assertSame('1.0.1', $result['new_version']);
        Process::mustRun(['composer', 'validate', '--no-check-version', '--no-check-publish'], $this->root);
        $committedLock = Process::mustRun(['git', 'show', 'HEAD:composer.lock'], $this->root)->stdout;
        self::assertSame(file_get_contents($this->root . '/composer.lock'), $committedLock);
        $changelog = file_get_contents($this->root . '/CHANGELOG.md');
        self::assertIsString($changelog);
        self::assertStringContainsString('- Hotfix the thing.', $changelog);
    }

    public function testExistingTagIsTreatedAsAlreadyReleased(): void
    {
        // VersionReleaser emits a GitHub Actions ::notice:: when the tag exists.
        $this->expectOutputRegex('/already (exists|released)/');

        Process::mustRun(['git', 'tag', 'v1.0.1'], $this->root);

        $project = new Project($this->root);
        $result = VersionReleaser::forProject($project)
            ->release('instant', 'patch', 'Anything.', '2026-06-09');

        self::assertTrue($result['already_released']);
        self::assertFalse($result['version_committed']);
        // Version unchanged because the release was skipped.
        self::assertSame('1.0.0', $project->version());
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function pushCases(): iterable
    {
        foreach (['changeset', 'instant'] as $mode) {
            yield "{$mode} publishes both refs" => [$mode, null];
            yield "{$mode} branch rejection" => [$mode, 'heads'];
            yield "{$mode} tag rejection" => [$mode, 'tags'];
        }
    }

    #[DataProvider('pushCases')]
    public function testCliReportsGitPublicationOutcome(string $mode, ?string $rejectedRef): void
    {
        if ($mode === 'changeset') {
            file_put_contents($this->root . '/changelog.d/a.md', "---\nbump: patch\n---\n- Fix a bug.\n");
            Process::mustRun(['git', 'add', 'changelog.d/a.md'], $this->root);
            Process::mustRun(['git', 'commit', '-qm', 'Add fragment'], $this->root);
            Process::mustRun(['git', 'push', '-q', 'origin', 'main'], $this->root);
        }
        $before = $this->remoteRef('refs/heads/main');

        if ($rejectedRef !== null) {
            $hook = $this->remote . '/hooks/update';
            file_put_contents($hook, "#!/bin/sh\ncase \"\$1\" in\n  refs/{$rejectedRef}/*) echo 'rejected by fixture' >&2; exit 1 ;;\nesac\n");
            chmod($hook, 0o755);
        }

        $output = $this->root . '/outputs';
        file_put_contents($output, '');
        // Project::locate() starts at the script source, so copy the CLI and
        // its bootstrap into the fixture to keep all release writes local.
        $scripts = $this->root . '/scripts';
        $source = \dirname(__DIR__, 2) . '/scripts';
        mkdir($scripts . '/src', 0o775, true);
        foreach (['bootstrap.php', 'version-and-commit.php'] as $file) {
            self::assertTrue(copy($source . '/' . $file, $scripts . '/' . $file));
        }
        foreach (glob($source . '/src/*.php') ?: [] as $file) {
            self::assertTrue(copy($file, $scripts . '/src/' . basename($file)));
        }

        $result = Process::run([
            'env',
            "GITHUB_OUTPUT={$output}",
            \PHP_BINARY,
            $scripts . '/version-and-commit.php',
            "--mode={$mode}",
        ], $this->root);
        $releaseCommit = Process::mustRun(['git', 'rev-parse', 'HEAD'], $this->root)->output();
        self::assertNotSame($before, $releaseCommit);

        if ($rejectedRef === null) {
            self::assertSame(0, $result->exitCode, $result->stderr);
            self::assertSame("new_version=1.0.1\nversion_committed=true\nalready_released=false\n", file_get_contents($output));
            self::assertSame($releaseCommit, $this->remoteRef('refs/tags/v1.0.1^{commit}'));
        } else {
            self::assertNotSame(0, $result->exitCode);
            self::assertStringContainsString('rejected by fixture', $result->stderr);
            self::assertSame('', file_get_contents($output));
            self::assertSame('', $this->remoteRef('refs/tags/v1.0.1'));
        }

        self::assertSame($rejectedRef === 'heads' ? $before : $releaseCommit, $this->remoteRef('refs/heads/main'));
    }

    private function remoteRef(string $ref): string
    {
        return Process::run(['git', '--git-dir', $this->remote, 'rev-parse', '--verify', '--quiet', $ref])->output();
    }
}
