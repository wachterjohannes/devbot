<?php

declare(strict_types=1);

namespace App\Memory\Search;

use App\Memory\MemoryManager;
use App\Memory\Model\MemoryEntry;

/**
 * Agentic search corpus for multi-hop RAG.
 * Tracks found set, pruned entries, and deduplication across calls.
 * Inspired by symfony/ai PR #1825 DocumentCorpus pattern.
 */
final class MemoryCorpus
{
    /** @var array<string, MemoryEntry> Working set — entries the agent has read */
    private array $workingSet = [];

    /** @var array<string, true> IDs seen in search results (dedup) */
    private array $seenIds = [];

    /** @var array<string, true> IDs explicitly pruned by the agent */
    private array $prunedIds = [];

    public function __construct(
        private readonly MemoryManager $memoryManager,
    ) {
    }

    /**
     * Semantic search, excluding already-seen and pruned entries.
     *
     * @return list<array{id: string, score: float, snippet: string, metadata: array<string, mixed>}>
     */
    public function search(string $query, int $limit = 10): array
    {
        $excludeIds = array_keys($this->prunedIds);
        $results = $this->memoryManager->semanticSearch($query, $limit + \count($this->seenIds), $excludeIds);

        // Filter already-seen, mark new ones as seen
        $filtered = [];
        foreach ($results as $result) {
            $id = $result['id'];
            if (isset($this->seenIds[$id])) {
                continue;
            }

            $this->seenIds[$id] = true;
            $filtered[] = $result;

            if (\count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    /**
     * Grep across long-term memory content for exact keyword matches.
     *
     * @return list<array{id: string, line: string, lineNumber: int}>
     */
    public function grep(string $pattern): array
    {
        $rawResults = $this->memoryManager->grep($pattern);

        $results = [];
        foreach ($rawResults as $match) {
            $id = $match['entry']->id;
            if (isset($this->prunedIds[$id])) {
                continue;
            }

            $this->seenIds[$id] = true;
            $results[] = [
                'id' => $id,
                'line' => $match['line'],
                'lineNumber' => $match['lineNumber'],
            ];
        }

        return $results;
    }

    /**
     * Read full content of a memory entry and add it to the working set.
     */
    public function read(string $id): ?MemoryEntry
    {
        if (isset($this->prunedIds[$id])) {
            return null;
        }

        $entry = $this->memoryManager->get($id);

        if ($entry !== null) {
            $this->workingSet[$id] = $entry;
            $this->seenIds[$id] = true;
        }

        return $entry;
    }

    /**
     * Prune an entry — exclude from all future search results this session.
     */
    public function prune(string $id): void
    {
        $this->prunedIds[$id] = true;
        unset($this->workingSet[$id]);
    }

    /**
     * Get the current working set summary for the agent.
     */
    public function getWorkingSetSummary(): string
    {
        if ($this->workingSet === []) {
            return 'Working set is empty. Use memory_search or memory_grep to find entries, then memory_read to add them.';
        }

        $lines = ['Current working set (' . \count($this->workingSet) . ' entries):'];

        foreach ($this->workingSet as $id => $entry) {
            $tags = $entry->metadata->tags !== [] ? ' [' . implode(', ', $entry->metadata->tags) . ']' : '';
            $lines[] = "- {$id}{$tags}: " . $entry->getSnippet(100);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, MemoryEntry>
     */
    public function getWorkingSet(): array
    {
        return $this->workingSet;
    }

    /**
     * Reset corpus state for a new search session.
     */
    public function reset(): void
    {
        $this->workingSet = [];
        $this->seenIds = [];
        $this->prunedIds = [];
    }
}
