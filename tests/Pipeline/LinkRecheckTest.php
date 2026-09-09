<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\LinkRecheck;
use LinkFoundation\Template\Pipeline\LycheeFailure;
use PHPUnit\Framework\TestCase;

final class LinkRecheckTest extends TestCase
{
    private const REPORT = <<<'MD'
        # Links

        | Summary        |
        | -------------- |
        | 2 errors       |

        ## Errors

        * [404] <https://example.com/gone> | Rejected status code: 404 Not Found
        * [ERROR] <https://example.com/reset> | Send error (at 2026-09-09 00:00:00) | Connection reset by peer
        * [TIMEOUT] <https://example.com/slow.> | Timeout
        * [UNKNOWN] <mailto:someone@example.com> | Unknown error

        [Full Github Actions output](https://github.com/owner/repo/actions/runs/1?check_suite_focus=true)
        MD;

    public function testAnsweredFailuresAreMarkedFinal(): void
    {
        $failures = LinkRecheck::parseLycheeFailures(self::REPORT);

        self::assertCount(4, $failures);

        $byUrl = [];
        foreach ($failures as $failure) {
            $byUrl[$failure->url] = $failure;
        }

        // A 404 means a host answered; the answer is final and the URL is
        // never re-checked.
        self::assertTrue($byUrl['https://example.com/gone']->answered);
        self::assertSame('404', $byUrl['https://example.com/gone']->marker);

        // Connect-phase resets, timeouts and unknowns never got an answer.
        self::assertFalse($byUrl['https://example.com/reset']->answered);
        self::assertFalse($byUrl['https://example.com/slow']->answered);
        self::assertFalse($byUrl['mailto:someone@example.com']->answered);
    }

    public function testDetailAloneMarksTheFailureAnswered(): void
    {
        $failures = LinkRecheck::parseLycheeFailures(
            '* [ERROR] <https://example.com/x> | Rejected status code: 503 Service Unavailable',
        );

        self::assertCount(1, $failures);
        self::assertTrue($failures[0]->answered);
    }

    public function testAcceptRangesFollowLycheeSyntax(): void
    {
        $accepts = LinkRecheck::parseAcceptRanges('100..=103,200..=299,429');

        self::assertTrue($accepts(100));
        self::assertTrue($accepts(103));
        self::assertTrue($accepts(200));
        self::assertTrue($accepts(299));
        self::assertTrue($accepts(429));
        self::assertFalse($accepts(104));
        self::assertFalse($accepts(399));
        self::assertFalse($accepts(404));
        self::assertFalse($accepts(500));
    }

    public function testRequestOptionsComeFromTheWorkflowFlags(): void
    {
        $workflow = <<<'YAML'
              - name: Check links with lychee
                uses: lycheeverse/lychee-action@v2
                with:
                  args: --accept "200..=299,403" --user-agent my-checker
            YAML;

        self::assertSame(
            ['200..=299,403', 'my-checker'],
            LinkRecheck::extractLycheeRequestOptions($workflow),
        );

        // The defaults must be lychee's documented ones, because this
        // repository's workflow does not set the flags at all.
        self::assertSame(
            [LinkRecheck::ACCEPT_DEFAULT, LinkRecheck::USER_AGENT_DEFAULT],
            LinkRecheck::extractLycheeRequestOptions('args: --verbose --no-progress'),
        );
    }

    public function testTheRecheckJudgesByTheSameRulesTheCheckerUsed(): void
    {
        $yaml = file_get_contents(\dirname(__DIR__, 2) . '/.github/workflows/links.yml');
        self::assertIsString($yaml, 'Missing links.yml.');

        // If the workflow ever sets --accept or --user-agent, the defaults
        // the re-check falls back to would silently drift from what lychee
        // judges by.
        self::assertSame(
            LinkRecheck::extractLycheeRequestOptions($yaml),
            [LinkRecheck::ACCEPT_DEFAULT, LinkRecheck::USER_AGENT_DEFAULT],
            'links.yml sets lychee request flags; the re-check defaults must be updated to match.',
        );
    }

    public function testRecheckRecoversAcceptedAnswersAndKeepsRetryingSilence(): void
    {
        $answers = [
            'https://example.com/recovered' => 200,
            'https://example.com/rejected' => 404,
            'https://example.com/silent' => null,
        ];

        $recheck = new LinkRecheck(static function (string $url) use ($answers): int {
            $answer = $answers[$url] ?? null;

            if ($answer === null) {
                throw new \RuntimeException('connection reset');
            }

            return $answer;
        });

        $result = $recheck->recheckUnanswered(
            ['https://example.com/recovered', 'https://example.com/rejected', 'https://example.com/silent'],
            '100..=103,200..=299',
            'lychee',
            budgetSeconds: 5.0,
            initialWaitMs: 1.0,
        );

        self::assertSame(['https://example.com/recovered'], $result['recovered']);
        self::assertSame('https://example.com/rejected', $result['still_broken'][0]['url']);
        self::assertSame(404, $result['still_broken'][0]['status']);
        // The URL that kept refusing to answer is the only thing the budget
        // can run out on; an answer would have ended it early.
        self::assertSame('https://example.com/silent', $result['still_broken'][1]['url']);
        self::assertNull($result['still_broken'][1]['status']);
    }

    public function testRecheckDeduplicatesUrls(): void
    {
        $asked = [];
        $recheck = new LinkRecheck(static function (string $url) use (&$asked): int {
            $asked[] = $url;

            return 204;
        });

        $result = $recheck->recheckUnanswered(
            ['https://example.com/dup', 'https://example.com/dup'],
            '200..=299',
            'lychee',
            budgetSeconds: 5.0,
            initialWaitMs: 1.0,
        );

        self::assertSame(['https://example.com/dup'], $result['recovered']);
        self::assertCount(1, $asked);
    }

    public function testSplitRecoveredUrlsDropsTheRecoveredOnesFromTheArchiveLookup(): void
    {
        $split = LinkRecheck::splitRecoveredUrls(
            ['https://example.com/a', 'https://example.com/b', 'https://example.com/c'],
            "https://example.com/b\n\nhttps://example.com/c\n",
        );

        self::assertSame(['https://example.com/a'], $split['remaining']);
        self::assertSame(['https://example.com/b', 'https://example.com/c'], $split['recovered']);
    }

    public function testEveryFailureTypeIsClassifiedByTheSameRule(): void
    {
        // [200] style markers are also "answered" -- the marker being numeric
        // is what matters, not that the answer was a failure.
        $failure = LinkRecheck::parseLycheeFailures('* [301] <https://example.com/moved> | Moved')[0] ?? null;

        self::assertInstanceOf(LycheeFailure::class, $failure);
        self::assertTrue($failure->answered);
    }
}
