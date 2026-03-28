<?php

declare(strict_types=1);

namespace App\Tool\Git;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Process\Process;

/**
 * Interact with GitHub via the `gh` CLI.
 * List issues/PRs, read comments, post comments, view PR details.
 * Requires `gh` CLI installed and authenticated.
 */
#[AsTool(
    name: 'github',
    description: 'Interact with GitHub: list issues/PRs, read and post comments, view details. Uses the gh CLI.',
)]
final readonly class GitHubTool
{
    public function __construct(
        private string $workingDirectory,
    ) {
    }

    /**
     * @param string      $operation  One of: list_issues, list_prs, view_issue, view_pr, list_comments, post_comment
     * @param string|null $repo       Repository (owner/repo). Null = detect from git remote.
     * @param int|null    $number     Issue or PR number (required for view/comment operations)
     * @param string|null $body       Comment body (required for post_comment)
     * @param string      $state      Filter state for list operations: "open", "closed", "all"
     *
     * @return array{output: string, exit_code: int}
     */
    public function __invoke(
        string $operation,
        ?string $repo = null,
        ?int $number = null,
        ?string $body = null,
        string $state = 'open',
    ): array {
        $args = match ($operation) {
            'list_issues' => ['gh', 'issue', 'list', '--state', $state, '--json', 'number,title,state,labels,assignees,updatedAt', ...$this->repoArgs($repo)],
            'list_prs' => ['gh', 'pr', 'list', '--state', $state, '--json', 'number,title,state,labels,reviewDecision,updatedAt', ...$this->repoArgs($repo)],
            'view_issue' => ['gh', 'issue', 'view', (string) $number, ...$this->repoArgs($repo)],
            'view_pr' => ['gh', 'pr', 'view', (string) $number, ...$this->repoArgs($repo)],
            'list_comments' => ['gh', 'issue', 'view', (string) $number, '--comments', ...$this->repoArgs($repo)],
            'post_comment' => ['gh', 'issue', 'comment', (string) $number, '--body', $body ?? '', ...$this->repoArgs($repo)],
            default => ['echo', "Unknown operation: {$operation}"],
        };

        $process = new Process($args, $this->workingDirectory);
        $process->setTimeout(30);
        $process->run();

        return [
            'output' => trim($process->getOutput() . $process->getErrorOutput()),
            'exit_code' => $process->getExitCode() ?? 1,
        ];
    }

    /**
     * @return list<string>
     */
    private function repoArgs(?string $repo): array
    {
        return $repo !== null ? ['--repo', $repo] : [];
    }
}
