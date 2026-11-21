<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Pipeline;

/**
 * Builds GitHub Release notes from a CHANGELOG.md section: adds a Packagist
 * badge and caps the body at GitHub's size limit. All methods are pure.
 */
final class ReleaseNotes
{
    /** GitHub rejects release bodies larger than ~125 000 bytes. */
    public const MAX_BYTES = 120_000;

    public static function packagistBadge(string $packageName): string
    {
        $slug = strtolower($packageName);

        return \sprintf(
            '[![Packagist Version](https://img.shields.io/packagist/v/%s.svg)](https://packagist.org/packages/%s)',
            $slug,
            $slug,
        );
    }

    public static function addBadge(string $body, string $packageName): string
    {
        if (str_contains($body, 'img.shields.io/packagist')) {
            return $body;
        }

        return self::packagistBadge($packageName) . "\n\n" . $body;
    }

    /**
     * Cap UTF-8 release notes by byte count. Reserve space for the changelog
     * notice and clip the notice itself if it exceeds the cap.
     */
    public static function capBytes(string $body, string $repository, string $tag, int $max = self::MAX_BYTES): string
    {
        if ($max <= 0) {
            return '';
        }

        if (\strlen($body) <= $max) {
            return $body;
        }

        $notice = "\n\n---\n\n_Release notes truncated. See the full "
            . "[CHANGELOG.md](https://github.com/{$repository}/blob/{$tag}/CHANGELOG.md)._\n";

        $budget = $max - \strlen($notice);
        if ($budget <= 0) {
            return self::utf8Prefix($notice, $max);
        }

        return self::utf8Prefix($body, $budget) . $notice;
    }

    private static function utf8Prefix(string $text, int $bytes): string
    {
        if (\strlen($text) <= $bytes) {
            return $text;
        }

        // A continuation byte at the first omitted position means the cut
        // falls inside a character. Move back to its leading byte.
        while ($bytes > 0 && (\ord($text[$bytes]) & 0xC0) === 0x80) {
            --$bytes;
        }

        return substr($text, 0, $bytes);
    }

    public static function build(
        string $version,
        ?string $changelogSection,
        string $packageName,
        string $repository,
        string $tag,
        bool $isSentinel,
    ): string {
        $body = $changelogSection !== null && trim($changelogSection) !== ''
            ? trim($changelogSection)
            : "Release {$version}.";

        if (!$isSentinel) {
            $body = self::addBadge($body, $packageName);
        }

        return self::capBytes($body, $repository, $tag);
    }
}
