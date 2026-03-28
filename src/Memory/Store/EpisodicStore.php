<?php

declare(strict_types=1);

namespace App\Memory\Store;

use App\Memory\Model\MemoryEntry;

/**
 * Chronological event log stored as JSON files per day.
 * Used for: "What happened last Tuesday?", "When did we decide X?", learning from past events.
 */
final class EpisodicStore
{
    public function __construct(
        private readonly string $baseDir,
    ) {
    }

    /**
     * Log an event. Entries are stored in daily files: {date}/{timestamp}-{type}.json
     */
    public function add(MemoryEntry $entry): void
    {
        $date = $entry->metadata->createdAt->format('Y-m-d');
        $dir = $this->baseDir . '/' . $date;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = $entry->metadata->createdAt->format('His');
        $eventType = $this->extractEventType($entry);
        $suffix = substr($entry->id, 0, 8);
        $filename = "{$timestamp}-{$eventType}-{$suffix}.json";

        file_put_contents(
            $dir . '/' . $filename,
            json_encode($entry->jsonSerialize(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
        );
    }

    public function get(string $id): ?MemoryEntry
    {
        foreach ($this->iterateAllFiles() as $file) {
            $data = $this->readFile($file);
            if ($data !== null && ($data['id'] ?? '') === $id) {
                return MemoryEntry::fromArray($data);
            }
        }

        return null;
    }

    /**
     * Get events for a specific date.
     *
     * @return list<MemoryEntry>
     */
    public function getByDate(string $date): array
    {
        $dir = $this->baseDir . '/' . $date;

        if (!is_dir($dir)) {
            return [];
        }

        $entries = [];
        foreach (glob($dir . '/*.json') as $file) {
            $data = $this->readFile($file);
            if ($data !== null) {
                $entries[] = MemoryEntry::fromArray($data);
            }
        }

        return $entries;
    }

    /**
     * Get recent events across all dates.
     *
     * @return list<MemoryEntry>
     */
    public function getRecent(int $limit = 20): array
    {
        $allFiles = [];
        foreach ($this->iterateAllFiles() as $file) {
            $allFiles[] = $file;
        }

        // Sort by filename descending (newest first) since format is date/time-based
        rsort($allFiles);
        $allFiles = \array_slice($allFiles, 0, $limit);

        $entries = [];
        foreach ($allFiles as $file) {
            $data = $this->readFile($file);
            if ($data !== null) {
                $entries[] = MemoryEntry::fromArray($data);
            }
        }

        return $entries;
    }

    /**
     * Keyword search across all episodic entries.
     *
     * @return list<MemoryEntry>
     */
    public function search(string $keyword): array
    {
        $keyword = mb_strtolower($keyword);
        $results = [];

        foreach ($this->iterateAllFiles() as $file) {
            $data = $this->readFile($file);
            if ($data !== null && str_contains(mb_strtolower($data['content'] ?? ''), $keyword)) {
                $results[] = MemoryEntry::fromArray($data);
            }
        }

        return $results;
    }

    /**
     * Remove events older than the given number of days.
     */
    public function purgeOlderThan(int $days): int
    {
        $cutoff = new \DateTimeImmutable("-{$days} days");
        $removed = 0;

        if (!is_dir($this->baseDir)) {
            return 0;
        }

        foreach (scandir($this->baseDir) ?: [] as $dateDir) {
            if ($dateDir === '.' || $dateDir === '..') {
                continue;
            }

            try {
                $dirDate = new \DateTimeImmutable($dateDir);
            } catch (\Exception) {
                continue;
            }

            if ($dirDate < $cutoff) {
                $dirPath = $this->baseDir . '/' . $dateDir;
                foreach (glob($dirPath . '/*.json') as $file) {
                    unlink($file);
                    ++$removed;
                }
                @rmdir($dirPath);
            }
        }

        return $removed;
    }

    /**
     * @return \Generator<string>
     */
    private function iterateAllFiles(): \Generator
    {
        if (!is_dir($this->baseDir)) {
            return;
        }

        foreach (scandir($this->baseDir) ?: [] as $dateDir) {
            if ($dateDir === '.' || $dateDir === '..') {
                continue;
            }

            $dirPath = $this->baseDir . '/' . $dateDir;
            if (!is_dir($dirPath)) {
                continue;
            }

            foreach (glob($dirPath . '/*.json') as $file) {
                yield $file;
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readFile(string $path): ?array
    {
        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);

        return \is_array($data) ? $data : null;
    }

    private function extractEventType(MemoryEntry $entry): string
    {
        $tags = $entry->metadata->tags;

        if ($tags !== []) {
            return preg_replace('/[^a-zA-Z0-9_]/', '_', $tags[0]) ?? 'event';
        }

        return 'event';
    }
}
