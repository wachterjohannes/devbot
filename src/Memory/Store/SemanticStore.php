<?php

declare(strict_types=1);

namespace App\Memory\Store;

use App\Memory\Model\MemoryEntry;
use App\Memory\Model\MemoryMetadata;
use App\Memory\Model\MemoryType;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\StoreInterface;

/**
 * Vector-indexed semantic store wrapping symfony/ai-sqlite-store.
 * All memory entries that are persisted get embedded and stored here for fuzzy/semantic search.
 */
class SemanticStore
{
    public function __construct(
        private readonly StoreInterface $store,
        private readonly VectorizerInterface $vectorizer,
    ) {
    }

    /**
     * Embed and store a memory entry for semantic search.
     */
    public function add(MemoryEntry $entry): void
    {
        $metadata = new Metadata();
        $metadata->setText($entry->content);
        $metadata->setSource($entry->id);
        $metadata['memory_type'] = $entry->type->value;
        $metadata['tags'] = implode(',', $entry->metadata->tags);
        $metadata['importance'] = $entry->metadata->importance;
        $metadata['topic'] = $entry->metadata->topic ?? '';

        $textDoc = new TextDocument(
            id: $entry->id,
            content: $entry->content,
            metadata: $metadata,
        );

        $vectorDoc = $this->vectorizer->vectorize($textDoc);
        $this->store->add($vectorDoc);
    }

    /**
     * Semantic search across all stored memory entries.
     *
     * @param list<string> $excludeIds IDs to exclude from results (dedup/prune)
     * @return list<array{id: string, score: float, snippet: string, metadata: array<string, mixed>}>
     */
    public function search(string $query, int $limit = 10, array $excludeIds = []): array
    {
        $vector = $this->vectorizer->vectorize($query);

        $queryObj = new \Symfony\AI\Store\Query\VectorQuery($vector);
        $results = $this->store->query($queryObj, ['maxItems' => $limit + \count($excludeIds)]);

        $output = [];
        foreach ($results as $doc) {
            if (\in_array($doc->getId(), $excludeIds, true)) {
                continue;
            }

            $output[] = [
                'id' => (string) $doc->getId(),
                'score' => $doc->getScore() ?? 0.0,
                'snippet' => mb_substr($doc->getMetadata()->getText() ?? '', 0, 200),
                'metadata' => [
                    'memory_type' => $doc->getMetadata()['memory_type'] ?? '',
                    'tags' => $doc->getMetadata()['tags'] ?? '',
                    'importance' => $doc->getMetadata()['importance'] ?? 0.5,
                    'topic' => $doc->getMetadata()['topic'] ?? '',
                ],
            ];

            if (\count($output) >= $limit) {
                break;
            }
        }

        return $output;
    }

    /**
     * Full-text keyword search via SQLite FTS5.
     *
     * @param list<string> $excludeIds
     * @return list<array{id: string, score: float, snippet: string}>
     */
    public function textSearch(string $query, int $limit = 10, array $excludeIds = []): array
    {
        $textQuery = new TextQuery($query);

        if (!$this->store->supports(TextQuery::class)) {
            return [];
        }

        $results = $this->store->query($textQuery, ['maxItems' => $limit + \count($excludeIds)]);

        $output = [];
        foreach ($results as $doc) {
            if (\in_array($doc->getId(), $excludeIds, true)) {
                continue;
            }

            $output[] = [
                'id' => (string) $doc->getId(),
                'score' => $doc->getScore() ?? 0.0,
                'snippet' => mb_substr($doc->getMetadata()->getText() ?? '', 0, 200),
            ];

            if (\count($output) >= $limit) {
                break;
            }
        }

        return $output;
    }

    /**
     * Remove a memory entry from the semantic store.
     */
    public function remove(string $id): void
    {
        $this->store->remove($id);
    }
}
