<?php

declare(strict_types=1);

namespace App\Tool\Git;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Process\Process;

/**
 * Interact with GitLab via the `glab` CLI.
 * List issues/MRs, read comments, post comments, view MR details.
 * Requires `glab` CLI installed and authenticated.
 */
#[AsTool(
    name: 'gitlab',
    description: 'Interact with GitLab: list issues/MRs, read and post comments, view details. Uses the glab CLI.',
)]
final readonly class GitLabTool
{
    public function __construct(
        private string $workingDirectory,
    ) {
    }

    /**
     * @param string      $operation  One of: list_issues, list_mrs, view_issue, view_mr, list_comments, post_comment
     * @param string|null $repo       Repository (group/project). Null = detect from git remote.
     * @param int|null    $number     Issue or MR number (required for view/comment operations)
     * @param string|null $body       Comment body (required for post_comment)
     * @param string      $state      Filter: "opened", "closed", "all"
     *
     * @return array{output: string, exit_code: int}
     */
    public function __invoke(
        string $operation,
        ?string $repo = null,
        ?int $number = null,
        ?string $body = null,
        string $state = 'opened',
    ): array {
        $args = match ($operation) {
            'list_issues' => ['glab', 'issue', 'list', '--state', $state, ...$this->repoArgs($repo)],
            'list_mrs' => ['glab', 'mr', 'list', '--state', $state, ...$this->repoArgs($repo)],
            'view_issue' => ['glab', 'issue', 'view', (string) $number, ...$this->repoArgs($repo)],
            'view_mr' => ['glab', 'mr', 'view', (string) $number, ...$this->repoArgs($repo)],
            'list_comments' => ['glab', 'issue', 'note', 'list', (string) $number, ...$this->repoArgs($repo)],
            'post_comment' => ['glab', 'issue', 'note', (string) $number, '--message', $body ?? '', ...$this->repoArgs($repo)],
            'mr_comment' => ['glab', 'mr', 'note', (string) $number, '--message', $body ?? '', ...$this->repoArgs($repo)],
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
