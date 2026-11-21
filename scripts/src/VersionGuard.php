<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Pipeline;

/** Compare the PR's composer.json version with the fetched base manifest. */
final class VersionGuard
{
    public static function check(Project $project, string $baseRef): string
    {
        $root = $project->root();
        $baseCommit = new Git($root)->fetchOriginBranch($baseRef);
        $basePath = Process::mustRun(['git', 'ls-tree', '--name-only', $baseCommit, '--', 'composer.json'], $root);

        $headPath = $project->composerJsonPath();
        $headFile = is_file($headPath) ? file_get_contents($headPath) : false;
        if ($headFile === false) {
            throw new \RuntimeException('Unable to read head composer.json.');
        }
        $headVersion = self::versionField($headFile, 'head composer.json');

        if ($basePath->stdout === '') {
            return 'composer.json does not exist on the base branch (new file); skipping version-modification check.';
        }

        $baseFile = Process::mustRun(['git', 'cat-file', 'blob', "{$baseCommit}:composer.json"], $root);
        $baseVersion = self::versionField($baseFile->stdout, 'base composer.json');

        if ($baseVersion !== $headVersion) {
            throw new \RuntimeException(\sprintf(
                'This pull request changes the "version" field in composer.json '
                . '(%s -> %s). Versions are managed automatically by the release '
                . 'pipeline from changelog fragments — add one with `composer changeset` '
                . 'instead of editing the version by hand.',
                $baseVersion ?? '(absent)',
                $headVersion ?? '(absent)',
            ));
        }

        return 'composer.json version field is unchanged in this PR.';
    }

    private static function versionField(string $json, string $source): ?string
    {
        try {
            $data = json_decode($json, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException("Invalid JSON in {$source}: " . $error->getMessage(), 0, $error);
        }

        if (!$data instanceof \stdClass) {
            throw new \RuntimeException("Expected a JSON object in {$source}.");
        }
        if (!property_exists($data, 'version')) {
            return null;
        }
        if (!\is_string($data->version)) {
            throw new \RuntimeException("The \"version\" field in {$source} must be a string.");
        }

        return $data->version;
    }
}
