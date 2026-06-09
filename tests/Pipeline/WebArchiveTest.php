<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\Http;
use LinkFoundation\Template\Pipeline\WebArchive;
use PHPUnit\Framework\TestCase;

final class WebArchiveTest extends TestCase
{
    /** No-op backoff so retry paths don't actually sleep during tests. */
    private static function noSleep(): callable
    {
        return static function (int $seconds): void {
        };
    }

    public function testExtractsUrlsAndSkipsArchiveLinks(): void
    {
        $report = <<<MD
            | https://example.com/missing | 404 |
            See https://web.archive.org/web/2020/https://example.com/missing
            Also (https://example.org/page).
            MD;

        $urls = WebArchive::extractUrls($report);

        self::assertContains('https://example.com/missing', $urls);
        self::assertContains('https://example.org/page', $urls);
        self::assertNotContains('https://web.archive.org/web/2020/https://example.com/missing', $urls);
    }

    public function testSnapshotReturnsUrlWhenAvailable(): void
    {
        $json = json_encode([
            'archived_snapshots' => [
                'closest' => ['available' => true, 'url' => 'http://web.archive.org/web/x'],
            ],
        ]);
        self::assertIsString($json);

        $archive = new WebArchive(new Http(static fn (): array => ['status' => 200, 'body' => $json]));

        self::assertSame('http://web.archive.org/web/x', $archive->snapshot('https://example.com'));
    }

    public function testSnapshotReturnsNullWhenMissing(): void
    {
        $archive = new WebArchive(new Http(static fn (): array => [
            'status' => 200,
            'body' => json_encode(['archived_snapshots' => []]) ?: '',
        ]), self::noSleep());

        self::assertNull($archive->snapshot('https://example.com'));
    }

    public function testSnapshotRetriesWhenApiIsRateLimited(): void
    {
        // The availability API answers HTTP 200 with an empty snapshot set when
        // rate-limited; the second attempt succeeds.
        $empty = json_encode(['archived_snapshots' => []]) ?: '';
        $found = json_encode([
            'archived_snapshots' => ['closest' => ['available' => true, 'url' => 'http://web.archive.org/web/ok']],
        ]) ?: '';

        $calls = 0;
        $http = new Http(static function () use (&$calls, $empty, $found): array {
            ++$calls;

            return ['status' => 200, 'body' => $calls === 1 ? $empty : $found];
        });

        $archive = new WebArchive($http, self::noSleep());

        self::assertSame('http://web.archive.org/web/ok', $archive->snapshot('https://example.com'));
        self::assertSame(2, $calls, 'should retry after the first inconclusive answer');
    }

    public function testSnapshotGivesUpAfterMaxAttempts(): void
    {
        $calls = 0;
        $http = new Http(static function () use (&$calls): array {
            ++$calls;

            return ['status' => 200, 'body' => json_encode(['archived_snapshots' => []]) ?: ''];
        });

        $archive = new WebArchive($http, self::noSleep());

        self::assertNull($archive->snapshot('https://example.com'));
        self::assertSame(WebArchive::MAX_ATTEMPTS, $calls);
    }

    public function testReviewPartitionsArchivedAndMissing(): void
    {
        $responses = [
            'https://archived.test' => ['status' => 200, 'body' => json_encode([
                'archived_snapshots' => ['closest' => ['available' => true, 'url' => 'http://snap']],
            ]) ?: ''],
            'https://missing.test' => ['status' => 200, 'body' => json_encode([
                'archived_snapshots' => [],
            ]) ?: ''],
        ];

        $http = new Http(static function (string $method, string $url) use ($responses): array {
            foreach ($responses as $needle => $response) {
                if (str_contains($url, rawurlencode($needle))) {
                    return $response;
                }
            }

            return ['status' => 404, 'body' => ''];
        });

        $result = (new WebArchive($http, self::noSleep()))->review(['https://archived.test', 'https://missing.test']);

        self::assertArrayHasKey('https://archived.test', $result['archived']);
        self::assertSame(['https://missing.test'], $result['missing']);
    }
}
