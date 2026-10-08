<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\Process;
use PHPUnit\Framework\TestCase;

final class LinkRecheckCliTest extends TestCase
{
    public function testARecoveredTransientFailureCannotHideAPermanentFailure(): void
    {
        $this->assertReportRecovery(includePermanentFailure: true);
    }

    public function testDuplicateTransientFailuresCanFullyRecover(): void
    {
        $this->assertReportRecovery(includePermanentFailure: false);
    }

    public function testTransportRecoveryCannotHideAPermanentFailure(): void
    {
        $this->assertReportRecovery(includePermanentFailure: true, marker: 'ERROR', includeDuplicate: false);
    }

    private function assertReportRecovery(bool $includePermanentFailure, string $marker = '503', bool $includeDuplicate = true): void
    {
        $root = \dirname(__DIR__, 2);
        $directory = sys_get_temp_dir() . '/link-recheck-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        self::assertIsString($address);
        fclose($socket);

        $server = proc_open(
            [PHP_BINARY, '-S', $address, $root . '/experiments/issue-18/link-server.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $directory . '/server.log', 'a'], 2 => ['file', $directory . '/server.log', 'a']],
            $pipes,
            $directory,
        );
        self::assertIsResource($server);
        fclose($pipes[0]);

        try {
            $ready = false;
            for ($attempt = 0; $attempt < 50; ++$attempt) {
                $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.02);
                if (\is_resource($connection)) {
                    fclose($connection);
                    $ready = true;

                    break;
                }
                usleep(20000);
            }
            self::assertTrue($ready, 'The bounded localhost fixture did not start.');

            $url = 'http://' . $address;
            $detail = $marker === 'ERROR' ? 'Connection reset' : 'Rejected status code: 503';
            $report = "* [{$marker}] <{$url}/recovered> | {$detail}\n";
            if ($includeDuplicate) {
                $report .= "* [429] <{$url}/recovered> | Too Many Requests\n";
            }
            if ($includePermanentFailure) {
                $report .= "* [404] <{$url}/gone> | Rejected status code: 404\n";
            }
            file_put_contents($directory . '/report.md', $report);
            touch($directory . '/outputs');

            $result = Process::run([
                'env',
                'LYCHEE_OUTPUT=' . $directory . '/report.md',
                'RECOVERED_OUTPUT=' . $directory . '/recovered.txt',
                'GITHUB_OUTPUT=' . $directory . '/outputs',
                'RECHECK_BUDGET_SECONDS=1',
                PHP_BINARY, $root . '/scripts/recheck-broken-links.php',
            ], $root);

            self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
            self::assertSame($url . "/recovered\n", file_get_contents($directory . '/recovered.txt'));
            self::assertSame("/recovered\n", file_get_contents($directory . '/requests.txt'), 'The 404 must never be re-requested.');
            $outputs = (string) file_get_contents($directory . '/outputs');
            self::assertSame(!$includePermanentFailure, str_contains($outputs, 'all_recovered=true'));
        } finally {
            proc_terminate($server);
            proc_close($server);
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
