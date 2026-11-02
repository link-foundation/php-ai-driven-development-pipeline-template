<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Pipeline;

/**
 * Keeps active workflow runners and action/tool versions explicit and current.
 * The release lookup is injectable so regression tests stay offline.
 *
 * A newer upstream release is a fact about the world, not about the commit
 * under test (issue #20). Outdated pins therefore block pull requests, where
 * a contributor can act, but only warn on push, schedule and manual runs so
 * `main` does not turn red without a change. Unpinned runner images, missing
 * release annotations and failed release lookups still fail on every event.
 */
final class WorkflowDependencies
{
    private const TOOL_INPUTS = [
        'zizmorcore/zizmor-action' => 'zizmorcore/zizmor',
        'trufflesecurity/trufflehog' => 'trufflesecurity/trufflehog',
    ];

    /** @var callable(string): string */
    private $latestRelease;

    /** @param (callable(string): string)|null $latestRelease */
    public function __construct(?callable $latestRelease = null)
    {
        $this->latestRelease = $latestRelease ?? static function (string $repository): string {
            // Some repositories also publish non-action releases (CodeQL
            // bundles, for example). Select the newest stable semantic tag,
            // rather than blindly trusting /releases/latest.
            $result = Process::mustRun([
                'gh', 'api', 'repos/' . $repository . '/releases', '--paginate',
                '--jq', '.[] | select(.draft == false and .prerelease == false) | .tag_name',
            ]);

            return self::newestRelease($result->stdout);
        };
    }

    /** @return list<string> */
    public static function runnerErrors(string $yaml): array
    {
        $errors = [];
        foreach (explode("\n", $yaml) as $index => $line) {
            $line = trim((string) preg_replace('/\s+#.*$/', '', $line));
            if (preg_match('/^(?:runs-on:|os:|-\s).*\b(?:ubuntu|windows|macos)-latest\b/', $line) === 1) {
                $errors[] = 'line ' . ($index + 1) . ': pin hosted runners to an explicit image such as ubuntu-24.04';
            }
        }

        return $errors;
    }

    /** @return list<array{repository: string, reference: string, line: int}> */
    public static function dependencies(string $yaml): array
    {
        $dependencies = [];
        $action = '';
        foreach (explode("\n", $yaml) as $index => $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            if (preg_match('/^\s*-\s/', $line) === 1) {
                $action = '';
            }

            $repository = '';
            $reference = '';
            if (preg_match('/^\s*(?:-\s*)?uses:\s*[\'\"]?([\w.-]+\/[\w.\/-]+)@([^\s\'\"#]+)/', $line, $match) === 1) {
                $parts = explode('/', $match[1]);
                $repository = $parts[0] . '/' . $parts[1];
                $reference = $match[2];
                $action = $repository;
            } elseif (preg_match('/^\s*(?:-\s*)?uses:\s*[\'\"]?docker:\/\/rhysd\/actionlint/', $line) === 1) {
                $repository = 'rhysd/actionlint';
                // Preserve the digest pin for security and check its readable
                // release annotation, rather than replacing it with a tag.
                if (preg_match('/#\s*(v?\d+\.\d+\.\d+)\s*$/', $line, $match) !== 1) {
                    throw new \RuntimeException('The actionlint image needs a release annotation such as # v1.7.12');
                }
                $reference = $match[1];
            } elseif (isset(self::TOOL_INPUTS[$action]) && preg_match('/^\s+version:\s*[\'\"]?([^\s\'\"#]+)/', $line, $match) === 1) {
                $repository = self::TOOL_INPUTS[$action];
                $reference = $match[1];
            } elseif (preg_match('/\bpipx run zizmor==([^\s\'\"\\\\]+)/', $line, $match) === 1) {
                $repository = 'zizmorcore/zizmor';
                $reference = $match[1];
            }

            if ($repository !== '') {
                $dependencies[] = ['repository' => $repository, 'reference' => $reference, 'line' => $index + 1];
            }
        }

        return $dependencies;
    }

    public static function newestRelease(string $tags): string
    {
        $latest = '';
        foreach (explode("\n", trim($tags)) as $tag) {
            $tag = trim($tag);
            if (preg_match('/^v?\d+\.\d+\.\d+$/', $tag) !== 1) {
                continue;
            }
            if ($latest === '' || version_compare(ltrim($tag, 'v'), ltrim($latest, 'v'), '>')) {
                $latest = $tag;
            }
        }
        if ($latest === '') {
            throw new \RuntimeException('No stable semantic release found');
        }

        return $latest;
    }

    /**
     * @param array<string, string> $workflows Workflow paths and their contents.
     * @return list<string>
     */
    public function check(array $workflows): array
    {
        return array_map(static fn (array $finding): string => $finding['message'], $this->findings($workflows));
    }

    /**
     * Like check(), but marks findings caused only by a newer upstream release
     * (external state) as `outdated`, so callers can decide how to gate them.
     *
     * @param array<string, string> $workflows Workflow paths and their contents.
     * @return list<array{message: string, outdated: bool}>
     */
    public function findings(array $workflows): array
    {
        $findings = [];
        $releases = [];
        $lookupErrors = [];
        foreach ($workflows as $file => $yaml) {
            foreach (self::runnerErrors($yaml) as $error) {
                $findings[] = ['message' => $file . ':' . $error, 'outdated' => false];
            }
            try {
                $dependencies = self::dependencies($yaml);
            } catch (\Throwable $error) {
                $findings[] = ['message' => $file . ': ' . $error->getMessage(), 'outdated' => false];

                continue;
            }
            foreach ($dependencies as $dependency) {
                $repository = $dependency['repository'];
                $location = $file . ':' . $dependency['line'];
                if (isset($lookupErrors[$repository])) {
                    $findings[] = ['message' => "{$location}: cannot check {$repository}: " . $lookupErrors[$repository], 'outdated' => false];

                    continue;
                }
                try {
                    if (!isset($releases[$repository])) {
                        $releases[$repository] = ($this->latestRelease)($repository);
                    }
                    $latest = $releases[$repository];
                    if (ltrim($dependency['reference'], 'v') !== ltrim($latest, 'v')) {
                        $findings[] = ['message' => "{$location}: {$repository}@{$dependency['reference']} must use the latest stable release {$latest}", 'outdated' => true];
                    }
                } catch (\Throwable $error) {
                    $lookupErrors[$repository] = $error->getMessage();
                    $findings[] = ['message' => "{$location}: cannot check {$repository}: " . $error->getMessage(), 'outdated' => false];
                }
            }
        }

        return $findings;
    }

    /**
     * Reports findings as annotations and returns the process exit code.
     * Outdated pins fail only when $gateOutdated is true (pull requests);
     * otherwise they become warnings plus a step summary listing the bumps.
     *
     * @param list<array{message: string, outdated: bool}> $findings
     */
    public static function report(array $findings, bool $gateOutdated): int
    {
        $failed = false;
        $warnings = [];
        foreach ($findings as $finding) {
            if ($finding['outdated'] && !$gateOutdated) {
                Actions::warning($finding['message']);
                $warnings[] = $finding['message'];

                continue;
            }
            Actions::error($finding['message']);
            $failed = true;
        }
        if ($warnings !== []) {
            Actions::summary("### Outdated workflow dependencies\n\n"
                . 'A newer upstream release is not caused by this commit, so this run only warns. '
                . 'Pull requests fail until the pins below are bumped. Dependabot proposes `uses:` action bumps; '
                . "the zizmor `version` input, the `pipx` pin and the actionlint image need a manual pull request.\n\n"
                . implode("\n", array_map(static fn (string $warning): string => '- ' . $warning, $warnings)));
        }

        return $failed ? 1 : 0;
    }
}
