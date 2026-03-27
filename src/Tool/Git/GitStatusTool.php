<?php

declare(strict_types=1);

namespace App\Tool\Git;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Process\Process;

/**
 * Show git status, log, or diff for the configured working directory.
 */
#[AsTool('git_status', 'Show git status, recent log, or diff for the working directory.')]
final readonly class GitStatusTool
{
    public function __construct(
        private string $workingDirectory,
    ) {
    }

    /**
     * @param string $command Git sub-command: "status", "log", "diff", "branch"
     * @param int    $limit   For log: number of recent commits (default 10)
     *
     * @return array{output: string, exit_code: int}
     */
    public function __invoke(string $command = 'status', int $limit = 10): array
    {
        $args = match ($command) {
            'status' => ['git', 'status', '--short'],
            'log' => ['git', 'log', '--oneline', '-' . $limit],
            'diff' => ['git', 'diff', '--stat'],
            'diff-staged' => ['git', 'diff', '--staged', '--stat'],
            'branch' => ['git', 'branch', '-a'],
            default => ['git', $command],
        };

        $process = new Process($args, $this->workingDirectory);
        $process->setTimeout(15);
        $process->run();

        return [
            'output' => trim($process->getOutput() . $process->getErrorOutput()),
            'exit_code' => $process->getExitCode() ?? 1,
        ];
    }
}
