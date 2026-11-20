<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Pipeline;

/**
 * Classifies the files changed by a push or pull request so the workflow can
 * skip irrelevant jobs (e.g. docs-only PRs skip lint/test/changeset gates).
 */
final class ChangeDetector
{
    /** Path prefixes whose changes never count as "code". */
    private const EXCLUDED_PREFIXES = ['changelog.d/', 'docs/', 'examples/', 'experiments/'];

    /**
     * Compute the changed file list from the git history, based on the GitHub
     * event environment.
     *
     * @return list<string>
     */
    public static function changedFiles(?string $cwd = null): array
    {
        $event = getenv('GITHUB_EVENT_NAME') ?: '';
        $baseSha = getenv('GITHUB_BASE_SHA') ?: '';
        $headSha = getenv('GITHUB_HEAD_SHA') ?: '';

        if ($event === 'pull_request' || $event === 'push') {
            foreach ([$baseSha, $headSha] as $sha) {
                if (!preg_match('/\A(?:[a-f0-9]{40}|[a-f0-9]{64})\z/', $sha)) {
                    throw new \RuntimeException("Missing or invalid base/head SHA for {$event} change detection.");
                }
            }

            $head = "{$headSha}^{commit}";
            if ($event === 'push' && trim($baseSha, '0') === '') {
                // An all-zero before SHA means the ref was created. Treat
                // every path in the new tree as changed.
                return self::listTree($head, $cwd);
            }

            $separator = $event === 'pull_request' ? '...' : '..';
            if ($event === 'pull_request'
                && Process::mustRun(['git', 'rev-parse', '--is-shallow-repository'], $cwd)->output() === 'true') {
                throw new \RuntimeException('A full Git history is required for pull request change detection.');
            }

            return self::diff("{$baseSha}^{commit}{$separator}{$head}", $cwd);
        }

        // Local use compares the latest commit to its first parent. Read the
        // stored header so a shallow checkout cannot masquerade as a root.
        $commit = Process::mustRun(['git', 'cat-file', 'commit', 'HEAD'], $cwd)->stdout;
        $header = explode("\n\n", $commit, 2)[0];
        if (preg_match('/^parent /m', $header) === 0) {
            return self::listTree('HEAD', $cwd);
        }

        return self::diff('HEAD^..HEAD', $cwd);
    }

    /** @return list<string> */
    private static function diff(string $range, ?string $cwd): array
    {
        return self::readPaths(['git', 'diff', '--name-only', '--no-renames', '-z', $range, '--'], $cwd);
    }

    /** @return list<string> */
    private static function listTree(string $commit, ?string $cwd): array
    {
        return self::readPaths(['git', 'ls-tree', '-r', '--name-only', '-z', $commit], $cwd);
    }

    /**
     * @param list<string> $files
     * @return array<string, bool>
     */
    public static function classify(array $files): array
    {
        $flags = [
            'php-changed' => false,
            'tests-changed' => false,
            'package-changed' => false,
            'docs-changed' => false,
            'workflow-changed' => false,
            'any-code-changed' => false,
        ];

        foreach ($files as $file) {
            if ($file === '') {
                continue;
            }

            if (str_ends_with($file, '.php')) {
                $flags['php-changed'] = true;
            }

            if (str_starts_with($file, 'tests/')) {
                $flags['tests-changed'] = true;
            }

            if (\in_array($file, ['composer.json', 'composer.lock'], true)) {
                $flags['package-changed'] = true;
            }

            if (str_starts_with($file, 'docs/') || str_ends_with($file, '.md')) {
                $flags['docs-changed'] = true;
            }

            if (str_starts_with($file, '.github/workflows/')) {
                $flags['workflow-changed'] = true;
            }

            if (self::isCode($file)) {
                $flags['any-code-changed'] = true;
            }
        }

        return $flags;
    }

    private static function isCode(string $file): bool
    {
        if (str_ends_with($file, '.md')) {
            return false;
        }

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($file, $prefix)) {
                return false;
            }
        }

        if (str_starts_with($file, '.github/workflows/')) {
            return true;
        }

        foreach (['.php', '.json', '.yml', '.yaml', '.neon', '.dist'] as $ext) {
            if (str_ends_with($file, $ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $command
     * @return list<string>
     */
    private static function readPaths(array $command, ?string $cwd): array
    {
        $paths = explode("\0", Process::mustRun($command, $cwd)->stdout);
        array_pop($paths);

        return $paths;
    }
}
