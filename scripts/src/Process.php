<?php

declare(strict_types=1);

namespace LinkFoundation\Template\Pipeline;

/**
 * Minimal wrapper around running external commands (mostly `git` and `gh`).
 *
 * Commands are passed as argument arrays and escaped, avoiding shell-injection
 * and quoting headaches.
 */
final class Process
{
    /**
     * @param list<string> $command
     */
    public static function run(array $command, ?string $cwd = null): ProcessResult
    {
        $cmd = implode(' ', array_map('escapeshellarg', $command));
        $capture = [];

        try {
            // Separate files let the child write either stream without waiting
            // for a pipe reader, including on platforms with blocking pipes.
            foreach ([1, 2] as $descriptor) {
                $stream = tmpfile();
                if ($stream === false) {
                    throw new \RuntimeException('Failed to open a temporary command output file.');
                }
                $capture[$descriptor] = $stream;
            }

            $proc = proc_open($cmd, [0 => ['pipe', 'r']] + $capture, $pipes, $cwd);
            if (!\is_resource($proc)) {
                throw new \RuntimeException('Failed to start command: ' . $cmd);
            }

            try {
                fclose($pipes[0]);
            } finally {
                $exitCode = proc_close($proc);
            }

            return new ProcessResult($exitCode, self::readOutput($capture[1]), self::readOutput($capture[2]));
        } finally {
            foreach ($capture as $stream) {
                fclose($stream);
            }
        }
    }

    /** @param resource $stream */
    private static function readOutput($stream): string
    {
        if (!rewind($stream)) {
            throw new \RuntimeException('Failed to rewind command output.');
        }

        $output = stream_get_contents($stream);
        if ($output === false) {
            throw new \RuntimeException('Failed to read command output.');
        }

        return $output;
    }

    /**
     * Run a command and throw if it exits non-zero.
     *
     * @param list<string> $command
     */
    public static function mustRun(array $command, ?string $cwd = null): ProcessResult
    {
        $result = self::run($command, $cwd);

        if (!$result->ok()) {
            throw new \RuntimeException(\sprintf(
                "Command failed (%d): %s\n%s",
                $result->exitCode,
                implode(' ', $command),
                $result->stderr,
            ));
        }

        return $result;
    }
}
