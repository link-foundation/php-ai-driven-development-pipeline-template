<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\Http;
use LinkFoundation\Template\Pipeline\WebArchive;
use PHPUnit\Framework\TestCase;

final class WebArchiveTest extends TestCase
{
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
        ]));

        self::assertNull($archive->snapshot('https://example.com'));
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

        $result = (new WebArchive($http))->review(['https://archived.test', 'https://missing.test']);

        self::assertArrayHasKey('https://archived.test', $result['archived']);
        self::assertSame(['https://missing.test'], $result['missing']);
    }
}
