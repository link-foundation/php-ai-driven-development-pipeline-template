<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Tests\Pipeline;

use LinkFoundation\Template\Pipeline\Process;
use PHPUnit\Framework\TestCase;

final class PipelineShellScriptsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 2);
    }

    /**
     * @param array<string, string> $environment
     */
    private function runScript(string $script, array $environment = []): \LinkFoundation\Template\Pipeline\ProcessResult
    {
        $command = ['/usr/bin/env'];
        foreach ($environment as $name => $value) {
            $command[] = "{$name}={$value}";
        }
        $command[] = 'bash';
        $command[] = $script;

        return Process::run($command, $this->root);
    }

    /** @param array<string, string> $environment */
    private function gate(string $workflow, string $runSha, string $headSha, array $environment = []): \LinkFoundation\Template\Pipeline\ProcessResult
    {
        return $this->runScript('scripts/check-pipeline-status.sh', $environment + [
            'NEEDS_JSON' => '{"build":{"result":"cancelled"}}',
            'RUN_SHA' => $runSha,
            'BRANCH_REF' => 'main',
            'BRANCH_HEAD_SHA' => $headSha,
            'WORKFLOW_FILE' => $workflow,
        ]);
    }

    public function testCancelledNonCancellableJobFailsEvenAfterBranchMoves(): void
    {
        $workflow = tempnam(sys_get_temp_dir(), 'workflow-');
        self::assertIsString($workflow);
        file_put_contents($workflow, <<<'YAML'
            name: Test
            concurrency:
              group: test
              cancel-in-progress: false
            jobs:
              build:
                runs-on: ubuntu-latest
            YAML);

        try {
            $result = $this->gate($workflow, str_repeat('1', 40), str_repeat('2', 40));
        } finally {
            unlink($workflow);
        }

        self::assertSame(1, $result->exitCode, $result->stdout . $result->stderr);
        self::assertStringContainsString('cancel-in-progress: false', $result->stdout);
    }

    public function testSupersedeOnlyExcusesJobThatCancelsInProgress(): void
    {
        $workflow = tempnam(sys_get_temp_dir(), 'workflow-');
        self::assertIsString($workflow);
        file_put_contents($workflow, "concurrency:\n  group: test\n  cancel-in-progress: true\njobs:\n  build:\n    runs-on: ubuntu-latest\n");

        try {
            $result = $this->gate($workflow, str_repeat('1', 40), str_repeat('2', 40));
        } finally {
            unlink($workflow);
        }

        self::assertSame(0, $result->exitCode, $result->stdout . $result->stderr);
        self::assertStringContainsString('Cancelled jobs in a superseded run', $result->stdout);
    }

    public function testCurrentRunCancellationFailsEvenWhenJobCancelsInProgress(): void
    {
        $workflow = tempnam(sys_get_temp_dir(), 'workflow-');
        self::assertIsString($workflow);
        file_put_contents($workflow, "concurrency:\n  group: test\n  cancel-in-progress: true\njobs:\n  build:\n    runs-on: ubuntu-latest\n");
        $sha = str_repeat('1', 40);

        try {
            $result = $this->gate($workflow, $sha, $sha);
        } finally {
            unlink($workflow);
        }

        self::assertSame(1, $result->exitCode, $result->stdout . $result->stderr);
        self::assertStringContainsString('still the head', $result->stdout);
    }

    public function testExpressionPolicyFailsClosed(): void
    {
        $workflow = tempnam(sys_get_temp_dir(), 'workflow-');
        self::assertIsString($workflow);
        file_put_contents($workflow, "concurrency:\n  group: test\n  cancel-in-progress: \${{ github.ref != 'refs/heads/main' }}\njobs:\n  build:\n    runs-on: ubuntu-latest\n");

        try {
            $result = $this->gate($workflow, str_repeat('1', 40), str_repeat('2', 40));
        } finally {
            unlink($workflow);
        }

        self::assertSame(1, $result->exitCode, $result->stdout . $result->stderr);
        self::assertStringContainsString('expression or otherwise unknown', $result->stdout);
    }

    public function testDetachedWorkerCannotHoldPipelineOutputOpen(): void
    {
        $work = sys_get_temp_dir() . '/budget-test-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($work));
        $marker = 'budget-worker-' . bin2hex(random_bytes(8));
        $command = $work . '/command.sh';
        $log = $work . '/step.log';
        file_put_contents($command, "#!/usr/bin/env bash\nphp -r 'sleep(10);' {$marker} &\necho root-finished\n");
        chmod($command, 0o700);

        $shell = \sprintf(
            'BUDGET_POLL_SECONDS=0.1 bash %s 2 worker bash %s 2>&1 | tee %s >/dev/null',
            escapeshellarg($this->root . '/scripts/run-with-budget-warning.sh'),
            escapeshellarg($command),
            escapeshellarg($log),
        );

        $output = false;
        try {
            $result = Process::run(['timeout', '3', 'bash', '-o', 'pipefail', '-c', $shell], $this->root);
            $output = file_get_contents($log);
        } finally {
            Process::run(['pkill', '-f', '[' . $marker[0] . ']' . substr($marker, 1)]);
            if (is_file($command)) {
                unlink($command);
            }
            if (is_file($log)) {
                unlink($log);
            }
            if (is_dir($work)) {
                rmdir($work);
            }
        }

        self::assertSame(0, $result->exitCode, 'The output pipeline stayed open after the command root exited.');
        self::assertIsString($output);
        self::assertStringContainsString('root-finished', $output);
    }

    public function testBudgetWrapperPreservesStreamsAndCommandStatus(): void
    {
        $result = Process::run([
            '/usr/bin/env',
            'BUDGET_POLL_SECONDS=0.1',
            'bash',
            'scripts/run-with-budget-warning.sh',
            '5',
            'stream probe',
            'bash',
            '-c',
            'printf stdout-value; printf stderr-value >&2; exit 7',
        ], $this->root);

        self::assertSame(7, $result->exitCode, $result->stdout . $result->stderr);
        self::assertStringContainsString('stdout-value', $result->stdout);
        self::assertStringNotContainsString('stderr-value', $result->stdout);
        self::assertStringContainsString('stderr-value', $result->stderr);
    }

    public function testBudgetWrapperKillsAnOverrunAndReturns124(): void
    {
        $marker = 'budget-overrun-' . bin2hex(random_bytes(8));
        $result = Process::run([
            'timeout',
            '4',
            '/usr/bin/env',
            'BUDGET_POLL_SECONDS=0.1',
            'BUDGET_GRACE_SECONDS=0',
            'BUDGET_KILL_SECONDS=1',
            'BUDGET_SUDO_KILL=0',
            'bash',
            'scripts/run-with-budget-warning.sh',
            '1',
            'overrun probe',
            'bash',
            '-c',
            'trap "" TERM; while :; do :; done',
            $marker,
        ], $this->root);

        self::assertSame(124, $result->exitCode, $result->stdout . $result->stderr);
        self::assertStringContainsString('exceeded its execution budget', $result->stdout);
        self::assertStringContainsString('sending SIGKILL', $result->stdout);
        $safePattern = '[' . $marker[0] . ']' . substr($marker, 1);
        self::assertSame(1, Process::run(['pgrep', '-f', $safePattern])->exitCode, 'The over-budget process survived.');
    }
}
