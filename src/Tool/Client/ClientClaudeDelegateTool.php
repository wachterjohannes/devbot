<?php

declare(strict_types=1);

namespace App\Tool\Client;

use App\Server\ClientConnectionRegistry;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Run Claude Code on the connected client's local machine.
 * The client executes `claude -p` locally so Claude has access to the client's
 * filesystem, git repos, and development environment.
 */
#[AsTool('client_claude_delegate', 'Run Claude Code on the connected client\'s machine. Use for coding, planning, or analysis of the client\'s local projects.')]
final readonly class ClientClaudeDelegateTool
{
    public function __construct(
        private ClientConnectionRegistry $registry,
    ) {
    }

    /**
     * @param string      $task             Task description
     * @param string|null $context          Additional context
     * @param string      $mode             "dev" (acceptEdits) or "plan" (read-only)
     * @param string|null $workingDirectory Working directory on the client (null = client's cwd)
     * @param string      $model            Claude model: "sonnet", "opus", "haiku"
     *
     * @return array{result: string, model: string, mode: string}|array{error: string}
     */
    public function __invoke(
        string $task,
        ?string $context = null,
        string $mode = 'dev',
        ?string $workingDirectory = null,
        string $model = 'sonnet',
    ): array {
        $client = $this->registry->getActiveClient();

        if ($client === null) {
            return ['error' => 'No client connected. A client must be connected via `bin/devbot client` for remote Claude execution.'];
        }

        $prompt = $task;
        if ($context !== null && $context !== '') {
            $prompt .= "\n\n## Context\n\n" . $context;
        }

        $permissionMode = match ($mode) {
            'plan' => 'plan',
            'auto' => 'auto',
            default => 'acceptEdits',
        };

        return $client->callTool('claude', 'run', [
            'prompt' => $prompt,
            'model' => $model,
            'permission_mode' => $permissionMode,
            'working_directory' => $workingDirectory,
        ]);
    }
}
