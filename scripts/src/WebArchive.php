<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Pipeline;

/**
 * Checks broken links against the Internet Archive's Wayback Machine.
 *
 * When lychee reports a broken link we do not fail immediately: a permanent
 * snapshot on archive.org is an acceptable replacement, so the link is only a
 * hard failure when no archived copy exists. This mirrors the JavaScript
 * template's web-archive fallback, rewritten as native PHP.
 */
final class WebArchive
{
    public const AVAILABILITY_API = 'https://archive.org/wayback/available';

    public function __construct(private readonly Http $http = new Http())
    {
    }

    /**
     * Extract candidate URLs from a lychee Markdown report.
     *
     * @return list<string>
     */
    public static function extractUrls(string $report): array
    {
        preg_match_all('#https?://[^\s)\]<>"\']+#', $report, $matches);

        $urls = [];

        foreach ($matches[0] as $url) {
            // Trim trailing punctuation that the regex may have captured.
            $url = rtrim($url, '.,;:');

            // Ignore the archive itself to avoid recursive suggestions.
            if (str_contains($url, 'web.archive.org') || str_contains($url, 'archive.org/wayback')) {
                continue;
            }

            $urls[$url] = true;
        }

        return array_keys($urls);
    }

    /**
     * Return the archived snapshot URL for $url, or null when none exists.
     */
    public function snapshot(string $url): ?string
    {
        $endpoint = self::AVAILABILITY_API . '?url=' . rawurlencode($url);
        $response = $this->http->get($endpoint);

        if ($response['status'] !== 200 || $response['body'] === '') {
            return null;
        }

        /** @var array{archived_snapshots?: array{closest?: array{available?: bool, url?: string}}} $data */
        $data = json_decode($response['body'], true) ?: [];
        $closest = $data['archived_snapshots']['closest'] ?? null;

        if (\is_array($closest) && ($closest['available'] ?? false) === true && isset($closest['url'])) {
            return (string) $closest['url'];
        }

        return null;
    }

    /**
     * Look up every URL and partition them into archived (url => snapshot) and
     * missing (list of urls with no snapshot).
     *
     * @param list<string> $urls
     * @return array{archived: array<string, string>, missing: list<string>}
     */
    public function review(array $urls): array
    {
        $archived = [];
        $missing = [];

        foreach ($urls as $url) {
            $snapshot = $this->snapshot($url);

            if ($snapshot !== null) {
                $archived[$url] = $snapshot;
            } else {
                $missing[] = $url;
            }
        }

        return ['archived' => $archived, 'missing' => $missing];
    }
}
