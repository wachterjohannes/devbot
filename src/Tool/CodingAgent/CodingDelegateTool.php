<?php

declare(strict_types=1);

namespace App\Tool\CodingAgent;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Delegates complex tasks to Claude Code (spawned as `claude -p` subprocess).
 * Supports coding, planning, architecture, analysis — any task needing deep reasoning.
 *
 * Modes:
 *  - "dev"  → full access (read/write files, run commands)
 *  - "plan" → read-only (analyze, plan, review — no file modifications)
 */
#[AsTool('claude_delegate', 'Delegate a complex task to Claude Code. Use for coding, planning, architecture, analysis, or debugging. Set mode to "plan" for read-only analysis.')]
final readonly class CodingDelegateTool
{
    public function __construct(
        private PlatformInterface $platform,
        private string $workingDirectory,
    ) {
    }

    /**
     * @param string      $task             Task description (coding, planning, analysis, etc.)
     * @param string|null $context          Additional context (file paths, constraints, goals)
     * @param string      $mode             "dev" (full access, default) or "plan" (read-only analysis)
     * @param string|null $workingDirectory Override working directory (null = default)
     * @param string      $model            Claude model: "sonnet", "opus", "haiku"
     *
     * @return array{result: string, model: string, mode: string, working_directory: string}
     */
    public function __invoke(
        string $task,
        ?string $context = null,
        string $mode = 'dev',
        ?string $workingDirectory = null,
        string $model = 'sonnet',
    ): array {
        $cwd = $workingDirectory ?? $this->workingDirectory;

        $prompt = $task;
        if ($context !== null && $context !== '') {
            $prompt .= "\n\n## Context\n\n" . $context;
        }

        $messages = new MessageBag(
            Message::ofUser($prompt),
        );

        $options = ['cwd' => $cwd];

        // Map DevBot modes to Claude Code --permission-mode values
        $permissionMode = match ($mode) {
            'plan' => 'plan',
            'dev' => 'acceptEdits',
            'auto' => 'auto',
            default => 'acceptEdits',
        };
        $options['permission_mode'] = $permissionMode;

        $result = $this->platform->invoke($model, $messages, $options)->getResult();
        $content = $result->getContent();

        return [
            'result' => \is_string($content) ? $content : (string) $content,
            'model' => $model,
            'mode' => $mode,
            'working_directory' => $cwd,
        ];
    }
}
