<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Logs all tool executions (success and failure) to a JSONL log file
 * and keeps an in-memory buffer for the TUI log viewer.
 */
final class ToolExecutionLogger
{
    /** @var list<array{time: string, tool: string, args: array<string, mixed>, status: string, result: string}> */
    private array $entries = [];

    public function __construct(
        private readonly string $logFile,
    ) {
    }

    #[AsEventListener]
    public function onToolCallSucceeded(ToolCallSucceeded $event): void
    {
        $toolName = $event->getMetadata()->getName();
        $result = $event->getResult()->getResult();
        $resultStr = \is_string($result) ? $result : (json_encode($result, \JSON_UNESCAPED_UNICODE) ?: '');

        if (mb_strlen($resultStr) > 500) {
            $resultStr = mb_substr($resultStr, 0, 500) . '...';
        }

        $entry = [
            'time' => (new \DateTimeImmutable())->format('H:i:s'),
            'tool' => $toolName,
            'args' => $this->summarizeArgs($event->getArguments()),
            'status' => 'ok',
            'result' => $resultStr,
        ];

        $this->entries[] = $entry;
        $this->appendToFile($entry);
    }

    #[AsEventListener]
    public function onToolCallFailed(ToolCallFailed $event): void
    {
        $entry = [
            'time' => (new \DateTimeImmutable())->format('H:i:s'),
            'tool' => $event->getMetadata()->getName(),
            'args' => $this->summarizeArgs($event->getArguments()),
            'status' => 'error',
            'result' => $event->getException()->getMessage(),
        ];

        $this->entries[] = $entry;
        $this->appendToFile($entry);
    }

    /**
     * @return list<array{time: string, tool: string, args: array<string, mixed>, status: string, result: string}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<array{time: string, tool: string, args: array<string, mixed>, status: string, result: string}>
     */
    public function getRecent(int $count = 50): array
    {
        return \array_slice($this->entries, -$count);
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function summarizeArgs(array $args): array
    {
        $summary = [];

        foreach ($args as $key => $value) {
            if (\is_string($value) && mb_strlen($value) > 100) {
                $summary[$key] = mb_substr($value, 0, 100) . '...';
            } elseif (\is_array($value)) {
                $summary[$key] = '[' . \count($value) . ' items]';
            } else {
                $summary[$key] = $value;
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function appendToFile(array $entry): void
    {
        $dir = \dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->logFile,
            json_encode($entry, \JSON_UNESCAPED_UNICODE) . "\n",
            \FILE_APPEND,
        );
    }
}
