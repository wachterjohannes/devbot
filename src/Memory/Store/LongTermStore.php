<?php

declare(strict_types=1);

namespace App\Memory\Store;

use App\Memory\Model\MemoryEntry;
use App\Memory\Model\MemoryMetadata;
use App\Memory\Model\MemoryType;

/**
 * File-based markdown store with JSON metadata index.
 * Organizes entries by topic: projects, decisions, patterns, preferences.
 * Human-readable, git-versionable.
 */
final class LongTermStore
{
    private const INDEX_FILE = 'index.json';

    /** @var array<string, array<string, mixed>> */
    private array $index = [];

    public function __construct(
        private readonly string $baseDir,
    ) {
        $this->loadIndex();
    }

    public function add(MemoryEntry $entry): void
    {
        $topic = $entry->metadata->topic ?? 'general';
        $dir = $this->baseDir . '/' . $this->sanitizePath($topic);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = $dir . '/' . $this->sanitizePath($entry->id) . '.md';
        file_put_contents($filePath, $entry->content);

        $this->index[$entry->id] = [
            'file' => $filePath,
            'topic' => $topic,
            'metadata' => $entry->metadata->jsonSerialize(),
        ];

        $this->saveIndex();
    }

    public function get(string $id): ?MemoryEntry
    {
        if (!isset($this->index[$id])) {
            return null;
        }

        $info = $this->index[$id];
        $content = is_file($info['file']) ? (file_get_contents($info['file']) ?: '') : '';

        $metadata = MemoryMetadata::fromArray($info['metadata'] ?? []);
        $metadata->topic = $info['topic'] ?? null;
        $metadata->touch();

        $this->index[$id]['metadata'] = $metadata->jsonSerialize();
        $this->saveIndex();

        return new MemoryEntry(
            id: $id,
            content: $content,
            type: MemoryType::LONG_TERM,
            metadata: $metadata,
        );
    }

    public function update(string $id, ?string $content = null, ?MemoryMetadata $metadata = null): bool
    {
        if (!isset($this->index[$id])) {
            return false;
        }

        if ($content !== null) {
            file_put_contents($this->index[$id]['file'], $content);
        }

        if ($metadata !== null) {
            $this->index[$id]['metadata'] = $metadata->jsonSerialize();
            $this->index[$id]['topic'] = $metadata->topic ?? $this->index[$id]['topic'];
        }

        $this->saveIndex();

        return true;
    }

    public function remove(string $id): bool
    {
        if (!isset($this->index[$id])) {
            return false;
        }

        $file = $this->index[$id]['file'];
        if (is_file($file)) {
            unlink($file);
        }

        unset($this->index[$id]);
        $this->saveIndex();

        return true;
    }

    /**
     * @param list<string>|null $tags
     * @return list<MemoryEntry>
     */
    public function list(?string $topic = null, ?array $tags = null, int $limit = 50): array
    {
        $results = [];

        foreach ($this->index as $id => $info) {
            if ($topic !== null && ($info['topic'] ?? '') !== $topic) {
                continue;
            }

            if ($tags !== null) {
                $entryTags = $info['metadata']['tags'] ?? [];
                if (array_intersect($tags, $entryTags) === []) {
                    continue;
                }
            }

            $content = is_file($info['file']) ? (file_get_contents($info['file']) ?: '') : '';

            $results[] = new MemoryEntry(
                id: $id,
                content: $content,
                type: MemoryType::LONG_TERM,
                metadata: MemoryMetadata::fromArray($info['metadata'] ?? []),
            );

            if (\count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Keyword search across all long-term memory content.
     *
     * @return list<array{entry: MemoryEntry, line: string, lineNumber: int}>
     */
    public function grep(string $pattern): array
    {
        $results = [];
        $pattern = mb_strtolower($pattern);

        foreach ($this->index as $id => $info) {
            if (!is_file($info['file'])) {
                continue;
            }

            $content = file_get_contents($info['file']) ?: '';
            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                if (str_contains(mb_strtolower($line), $pattern)) {
                    $results[] = [
                        'entry' => new MemoryEntry(
                            id: $id,
                            content: $content,
                            type: MemoryType::LONG_TERM,
                            metadata: MemoryMetadata::fromArray($info['metadata'] ?? []),
                        ),
                        'line' => $line,
                        'lineNumber' => $lineNum + 1,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    public function getAllIds(): array
    {
        return array_keys($this->index);
    }

    private function loadIndex(): void
    {
        $indexPath = $this->baseDir . '/' . self::INDEX_FILE;

        if (!is_file($indexPath)) {
            $this->index = [];

            return;
        }

        $json = file_get_contents($indexPath) ?: '{}';
        $this->index = json_decode($json, true) ?: [];
    }

    private function saveIndex(): void
    {
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }

        file_put_contents(
            $this->baseDir . '/' . self::INDEX_FILE,
            json_encode($this->index, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
        );
    }

    private function sanitizePath(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name) ?? $name;
    }
}
