<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\ReleaseNotes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReleaseNotesTest extends TestCase
{
    private const NOTICE_PREFIX = "\n\n---\n\n_Release notes truncated. See the full "
        . '[CHANGELOG.md](https://github.com/owner/repo/blob/';
    private const NOTICE = self::NOTICE_PREFIX . "v1.0.0/CHANGELOG.md)._\n";

    public function testBadgeIsLowercasedAndLinksToPackagist(): void
    {
        $badge = ReleaseNotes::packagistBadge('Vendor/Pkg');

        self::assertStringContainsString('packagist/v/vendor/pkg.svg', $badge);
        self::assertStringContainsString('packagist.org/packages/vendor/pkg', $badge);
    }

    public function testAddBadgeIsIdempotent(): void
    {
        $once = ReleaseNotes::addBadge('Body.', 'vendor/pkg');
        $twice = ReleaseNotes::addBadge($once, 'vendor/pkg');

        self::assertSame(substr_count($once, 'img.shields.io'), substr_count($twice, 'img.shields.io'));
    }

    public function testBuildSkipsBadgeForSentinel(): void
    {
        $notes = ReleaseNotes::build('1.0.0', "### Added\n- Thing.", 'vendor/pkg', 'owner/repo', 'v1.0.0', true);

        self::assertStringNotContainsString('img.shields.io', $notes);
        self::assertStringContainsString('- Thing.', $notes);
    }

    public function testBuildFallsBackWhenSectionEmpty(): void
    {
        $notes = ReleaseNotes::build('1.0.0', null, 'vendor/pkg', 'owner/repo', 'v1.0.0', true);

        self::assertStringContainsString('Release 1.0.0.', $notes);
    }

    public function testCapBytesTruncatesAndLinksToChangelog(): void
    {
        $body = str_repeat('a', 5000);
        $capped = ReleaseNotes::capBytes($body, 'owner/repo', 'v1.0.0', 4000);

        self::assertLessThanOrEqual(4000, \strlen($capped));
        self::assertStringContainsString('CHANGELOG.md', $capped);
        self::assertStringContainsString('owner/repo', $capped);
    }

    public function testCapBytesLeavesShortBodyUntouched(): void
    {
        foreach (['short', 'α🙂中'] as $body) {
            self::assertSame($body, ReleaseNotes::capBytes($body, 'owner/repo', 'v1.0.0', \strlen($body)));
        }
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function characterCuts(): iterable
    {
        foreach (['é', '中', '🙂'] as $character) {
            for ($bytes = 1; $bytes <= \strlen($character); ++$bytes) {
                yield "{$character}, {$bytes} bytes" => [
                    $character,
                    $bytes,
                    $bytes === \strlen($character) ? $character : '',
                ];
            }
        }
    }

    #[DataProvider('characterCuts')]
    public function testCapBytesPreservesOnlyCompleteCharacters(string $character, int $bytes, string $expected): void
    {
        $body = 'prefix' . $character . str_repeat('x', 200);
        $max = \strlen('prefix' . self::NOTICE) + $bytes;

        $capped = ReleaseNotes::capBytes($body, 'owner/repo', 'v1.0.0', $max);

        self::assertSame(1, preg_match('//u', $capped));
        self::assertSame('prefix' . $expected . self::NOTICE, $capped);
    }

    public function testDefaultCapPreservesTheCompleteCharacterAtTheLimit(): void
    {
        $prefix = str_repeat('x', 120_000 - \strlen('é' . self::NOTICE)) . 'é';
        $capped = ReleaseNotes::capBytes($prefix . str_repeat('x', 1000), 'owner/repo', 'v1.0.0');

        self::assertSame(120_000, \strlen($capped));
        self::assertSame(1, preg_match('//u', $capped));
        self::assertSame($prefix . self::NOTICE, $capped);
    }

    public function testTinyCapsAlsoLimitTheNotice(): void
    {
        foreach ([0, 1, 7, \strlen(self::NOTICE) - 1, \strlen(self::NOTICE)] as $max) {
            self::assertSame(
                substr(self::NOTICE, 0, $max),
                ReleaseNotes::capBytes(str_repeat('x', 200), 'owner/repo', 'v1.0.0', $max),
            );
        }
    }

    #[DataProvider('characterCuts')]
    public function testClippedNoticePreservesOnlyCompleteCharacters(string $character, int $bytes, string $expected): void
    {
        $max = \strlen(self::NOTICE_PREFIX) + $bytes;
        $capped = ReleaseNotes::capBytes(str_repeat('x', 200), 'owner/repo', $character, $max);

        self::assertSame(1, preg_match('//u', $capped));
        self::assertSame(self::NOTICE_PREFIX . $expected, $capped);
    }
}
