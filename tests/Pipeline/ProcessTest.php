<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\Process;
use PHPUnit\Framework\TestCase;

final class ProcessTest extends TestCase
{
    public function testFiniteStderrFirstChildCompletesAndPreservesBothStreams(): void
    {
        // The deadline covers the whole process group if either capture deadlocks.
        $probe = <<<'PHP'
            require $argv[1];
            $result = \LinkFoundation\Template\Pipeline\Process::run([
                PHP_BINARY, '-d', 'memory_limit=32M', '-r',
                'fwrite(STDERR, str_repeat("E", 131072)); fwrite(STDOUT, str_repeat("O", 131072));',
            ]);
            echo json_encode([$result->exitCode, hash('sha256', $result->stdout), hash('sha256', $result->stderr)]);
            PHP;
        $result = Process::run([
            'timeout', '--kill-after=1s', '5s', PHP_BINARY, '-d', 'memory_limit=32M',
            '-r', $probe, \dirname(__DIR__, 2) . '/scripts/bootstrap.php',
        ]);

        self::assertSame(0, $result->exitCode, 'Process capture must finish within the deadline. ' . $result->stderr);
        self::assertSame(
            [0, hash('sha256', str_repeat('O', 131072)), hash('sha256', str_repeat('E', 131072))],
            json_decode($result->stdout, true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    public function testLiteralZeroAndFailingExitCodeArePreserved(): void
    {
        $result = Process::run([PHP_BINARY, '-r', 'echo "0"; fwrite(STDERR, "0"); exit(7);']);

        self::assertSame(7, $result->exitCode);
        self::assertSame('0', $result->stdout);
        self::assertSame('0', $result->stderr);
    }

    public function testInputClosesAndOutputPreservesBinaryBytes(): void
    {
        $result = Process::run([PHP_BINARY, '-r', 'echo stream_get_contents(STDIN), "small\x00output\n";']);

        self::assertSame(0, $result->exitCode);
        self::assertSame("small\x00output\n", $result->stdout);
        self::assertSame('', $result->stderr);
    }
}
