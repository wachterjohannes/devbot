<?php

declare(strict_types=1);

namespace App\Memory;

use App\Memory\Model\MemoryEntry;
use App\Memory\Model\MemoryMetadata;
use App\Memory\Model\MemoryType;
use App\Memory\Store\EpisodicStore;
use App\Memory\Store\LongTermStore;
use App\Memory\Store\SemanticStore;
use App\Memory\Store\ShortTermStore;

/**
 * Facade for the entire memory system.
 * Routes operations to the appropriate store(s) based on memory type.
 * All persisted entries are also indexed in the semantic store for search.
 */
final class MemoryManager
{
    public function __construct(
        private readonly ShortTermStore $shortTerm,
        private readonly LongTermStore $longTerm,
        private readonly EpisodicStore $episodic,
        private readonly SemanticStore $semantic,
    ) {
    }

    /**
     * Add a memory entry, routing to the correct store and indexing in semantic store.
     */
    public function add(MemoryEntry $entry): void
    {
        match ($entry->type) {
            MemoryType::SHORT_TERM => $this->shortTerm->add($entry),
            MemoryType::LONG_TERM => $this->addToLongTermAndSemantic($entry),
            MemoryType::EPISODIC => $this->addToEpisodicAndSemantic($entry),
            MemoryType::SEMANTIC => $this->semantic->add($entry),
        };
    }

    /**
     * Create and add a new long-term memory from raw content.
     *
     * @param list<string> $tags
     */
    public function remember(
        string $content,
        string $topic = 'general',
        array $tags = [],
        float $importance = 0.5,
        ?string $source = null,
    ): MemoryEntry {
        $entry = new MemoryEntry(
            id: 'lt-' . bin2hex(random_bytes(8)),
            content: $content,
            type: MemoryType::LONG_TERM,
            metadata: new MemoryMetadata(
                importance: $importance,
                tags: $tags,
                source: $source,
                topic: $topic,
            ),
        );

        $this->add($entry);

        return $entry;
    }

    /**
     * Log an episodic event.
     *
     * @param list<string> $tags
     */
    public function logEvent(
        string $content,
        array $tags = [],
        float $importance = 0.4,
        ?string $source = null,
    ): MemoryEntry {
        $entry = new MemoryEntry(
            id: 'ep-' . bin2hex(random_bytes(8)),
            content: $content,
            type: MemoryType::EPISODIC,
            metadata: new MemoryMetadata(
                importance: $importance,
                tags: $tags,
                source: $source,
            ),
        );

        $this->add($entry);

        return $entry;
    }

    /**
     * Get a memory entry by ID, searching across all stores.
     */
    public function get(string $id): ?MemoryEntry
    {
        // Route by prefix convention
        if (str_starts_with($id, 'st-')) {
            return $this->shortTerm->get($id);
        }

        if (str_starts_with($id, 'ep-')) {
            return $this->episodic->get($id);
        }

        // Default: try long-term first, then episodic
        return $this->longTerm->get($id) ?? $this->episodic->get($id);
    }

    /**
     * Update a long-term memory entry.
     */
    public function update(string $id, ?string $content = null, ?MemoryMetadata $metadata = null): bool
    {
        $updated = $this->longTerm->update($id, $content, $metadata);

        if ($updated && $content !== null) {
            // Re-index in semantic store
            $entry = $this->longTerm->get($id);
            if ($entry !== null) {
                $this->semantic->remove($id);
                $this->semantic->add($entry);
            }
        }

        return $updated;
    }

    /**
     * Remove a memory entry from all stores.
     */
    public function remove(string $id): bool
    {
        $removed = $this->longTerm->remove($id);
        $this->semantic->remove($id);

        return $removed;
    }

    /**
     * List entries, optionally filtered by type, topic, and tags.
     *
     * @param list<string>|null $tags
     * @return list<MemoryEntry>
     */
    public function list(
        ?MemoryType $type = null,
        ?string $topic = null,
        ?array $tags = null,
        int $limit = 50,
    ): array {
        if ($type === MemoryType::SHORT_TERM) {
            return $this->shortTerm->getAll();
        }

        if ($type === MemoryType::EPISODIC) {
            return $this->episodic->getRecent($limit);
        }

        // Default to long-term
        return $this->longTerm->list($topic, $tags, $limit);
    }

    /**
     * Semantic search across all persisted memory.
     *
     * @param list<string> $excludeIds
     * @return list<array{id: string, score: float, snippet: string, metadata: array<string, mixed>}>
     */
    public function semanticSearch(string $query, int $limit = 10, array $excludeIds = []): array
    {
        return $this->semantic->search($query, $limit, $excludeIds);
    }

    /**
     * Keyword search across long-term memory content.
     *
     * @return list<array{entry: MemoryEntry, line: string, lineNumber: int}>
     */
    public function grep(string $pattern): array
    {
        return $this->longTerm->grep($pattern);
    }

    public function getShortTerm(): ShortTermStore
    {
        return $this->shortTerm;
    }

    private function addToLongTermAndSemantic(MemoryEntry $entry): void
    {
        $this->longTerm->add($entry);
        $this->semantic->add($entry);
    }

    private function addToEpisodicAndSemantic(MemoryEntry $entry): void
    {
        $this->episodic->add($entry);
        $this->semantic->add($entry);
    }
}
