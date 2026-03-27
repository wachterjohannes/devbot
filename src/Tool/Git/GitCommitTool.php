<?php

declare(strict_types=1);

namespace App\Tool\Git;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Process\Process;

/**
 * Stage files and create a git commit in the working directory.
 */
#[AsTool('git_commit', 'Stage files and create a git commit. Provide file paths and a commit message.')]
final readonly class GitCommitTool
{
    public function __construct(
        private string $workingDirectory,
    ) {
    }

    /**
     * @param string       $message Commit message
     * @param list<string> $files   Files to stage (empty = all tracked changes)
     *
     * @return array{committed: bool, output: string}
     */
    public function __invoke(string $message, array $files = []): array
    {
        // Stage files
        if ($files === []) {
            $addProcess = new Process(['git', 'add', '-u'], $this->workingDirectory);
        } else {
            $addProcess = new Process(['git', 'add', ...$files], $this->workingDirectory);
        }

        $addProcess->setTimeout(15);
        $addProcess->run();

        if ($addProcess->getExitCode() !== 0) {
            return [
                'committed' => false,
                'output' => 'Stage failed: ' . trim($addProcess->getErrorOutput()),
            ];
        }

        // Commit
        $commitProcess = new Process(['git', 'commit', '-m', $message], $this->workingDirectory);
        $commitProcess->setTimeout(30);
        $commitProcess->run();

        return [
            'committed' => $commitProcess->getExitCode() === 0,
            'output' => trim($commitProcess->getOutput() . $commitProcess->getErrorOutput()),
        ];
    }
}
