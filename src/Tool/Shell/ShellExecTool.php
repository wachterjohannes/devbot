<?php

declare(strict_types=1);

namespace App\Tool\Shell;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Process\Process;

/**
 * Execute shell commands in a sandboxed environment.
 * Restricted to an allowlist of safe commands. Runs in DEVBOT_WORKDIR.
 */
#[AsTool('shell_exec', 'Execute a shell command in the working directory. Restricted to safe commands (git, composer, php, npm, make, grep, find, cat, ls, wc, head, tail, sort, uniq, diff).')]
final readonly class ShellExecTool
{
    private const ALLOWED_COMMANDS = [
        'git', 'composer', 'php', 'npm', 'npx', 'node',
        'make', 'grep', 'find', 'cat', 'ls', 'wc',
        'head', 'tail', 'sort', 'uniq', 'diff', 'echo',
        'mkdir', 'touch', 'pwd', 'date', 'which',
    ];

    public function __construct(
        private string $workingDirectory,
        private int $timeout = 30,
    ) {
    }

    /**
     * @param string $command The shell command to execute
     *
     * @return array{output: string, exit_code: int, error: string}
     */
    public function __invoke(string $command): array
    {
        $binary = $this->extractBinary($command);

        if (!\in_array($binary, self::ALLOWED_COMMANDS, true)) {
            return [
                'output' => '',
                'exit_code' => 1,
                'error' => "Command '{$binary}' is not allowed. Allowed: " . implode(', ', self::ALLOWED_COMMANDS),
            ];
        }

        $process = Process::fromShellCommandline($command, $this->workingDirectory);
        $process->setTimeout($this->timeout);
        $process->run();

        return [
            'output' => trim($process->getOutput()),
            'exit_code' => $process->getExitCode() ?? 1,
            'error' => trim($process->getErrorOutput()),
        ];
    }

    private function extractBinary(string $command): string
    {
        // Strip leading env vars (e.g. "APP_ENV=test php ...")
        $command = preg_replace('/^(\w+=\S+\s+)*/', '', $command) ?? $command;

        // Get the first word
        $parts = preg_split('/\s+/', trim($command), 2);

        return basename($parts[0] ?? '');
    }
}
