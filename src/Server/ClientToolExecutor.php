<?php

declare(strict_types=1);

namespace App\Server;

use Symfony\Component\Process\Process;

/**
 * Executes tool requests on the client's local machine.
 * Handles filesystem and shell operations forwarded from the headless server.
 */
final readonly class ClientToolExecutor
{
    private const ALLOWED_COMMANDS = [
        'git', 'composer', 'php', 'npm', 'npx', 'node',
        'make', 'grep', 'find', 'cat', 'ls', 'wc',
        'head', 'tail', 'sort', 'uniq', 'diff', 'echo',
        'mkdir', 'touch', 'pwd', 'date', 'which',
    ];

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function execute(array $request): array
    {
        $tool = $request['tool'] ?? '';
        $operation = $request['operation'] ?? '';
        $args = $request['args'] ?? [];

        return match ($tool) {
            'shell' => $this->handleShell($operation, $args),
            'filesystem' => $this->handleFilesystem($operation, $args),
            'claude' => $this->handleClaude($operation, $args),
            default => ['type' => 'tool_response', 'error' => "Unknown tool: {$tool}"],
        };
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function handleShell(string $operation, array $args): array
    {
        if ($operation !== 'exec') {
            return ['type' => 'tool_response', 'error' => "Unknown shell operation: {$operation}"];
        }

        $command = $args['command'] ?? '';
        $binary = $this->extractBinary($command);

        if (!\in_array($binary, self::ALLOWED_COMMANDS, true)) {
            return [
                'type' => 'tool_response',
                'output' => '',
                'exit_code' => 1,
                'error' => "Command '{$binary}' is not allowed on client.",
            ];
        }

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(30);
        $process->run();

        return [
            'type' => 'tool_response',
            'output' => trim($process->getOutput()),
            'exit_code' => $process->getExitCode() ?? 1,
            'error' => trim($process->getErrorOutput()),
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function handleFilesystem(string $operation, array $args): array
    {
        $path = $args['path'] ?? '';

        return match ($operation) {
            'read' => $this->readFile($path),
            'list' => $this->listDir($path),
            default => ['type' => 'tool_response', 'error' => "Unknown filesystem operation: {$operation}"],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function readFile(string $path): array
    {
        if (!is_file($path)) {
            return ['type' => 'tool_response', 'error' => "File not found: {$path}"];
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return ['type' => 'tool_response', 'error' => "Cannot read file: {$path}"];
        }

        // Truncate very large files
        if (mb_strlen($content) > 50000) {
            $content = mb_substr($content, 0, 50000) . "\n\n... (truncated, file too large)";
        }

        return [
            'type' => 'tool_response',
            'content' => $content,
            'path' => $path,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listDir(string $path): array
    {
        if (!is_dir($path)) {
            return ['type' => 'tool_response', 'error' => "Directory not found: {$path}"];
        }

        $entries = scandir($path) ?: [];
        $files = array_values(array_filter($entries, static fn (string $e) => $e !== '.' && $e !== '..'));

        return [
            'type' => 'tool_response',
            'files' => $files,
            'path' => $path,
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function handleClaude(string $operation, array $args): array
    {
        if ($operation !== 'run') {
            return ['type' => 'tool_response', 'error' => "Unknown claude operation: {$operation}"];
        }

        $prompt = $args['prompt'] ?? '';
        $model = $args['model'] ?? 'sonnet';
        $permissionMode = $args['permission_mode'] ?? 'acceptEdits';
        $cwd = $args['working_directory'] ?? null;

        // Build claude command
        $command = ['claude', '-p', '--output-format', 'text', '--model', $model, '--permission-mode', $permissionMode];

        if ($cwd === null) {
            $cwd = getcwd() ?: null;
        }

        $process = new Process($command, $cwd);
        $process->setInput($prompt);
        $process->setTimeout(300);
        $process->run();

        if ($process->getExitCode() !== 0 && $process->getOutput() === '') {
            return [
                'type' => 'tool_response',
                'error' => 'Claude Code failed: ' . trim($process->getErrorOutput()),
            ];
        }

        return [
            'type' => 'tool_response',
            'result' => trim($process->getOutput()),
            'model' => $model,
            'mode' => $permissionMode,
        ];
    }

    private function extractBinary(string $command): string
    {
        $command = preg_replace('/^(\w+=\S+\s+)*/', '', $command) ?? $command;
        $parts = preg_split('/\s+/', trim($command), 2);

        return basename($parts[0] ?? '');
    }
}
