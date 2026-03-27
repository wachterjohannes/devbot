<?php

declare(strict_types=1);

namespace App\Tool\Memory;

use App\Memory\Search\MemoryCorpus;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Semantic vector search across memory. Returns snippets ranked by relevance.
 * Already-seen and pruned results are automatically excluded (dedup across calls).
 * Does NOT add to working set — use memory_read for that.
 */
#[AsTool('memory_search', 'Semantic search across memory. Returns ranked snippets. Use for broad topic discovery.')]
final readonly class MemorySearchTool
{
    public function __construct(
        private MemoryCorpus $corpus,
    ) {
    }

    /**
     * @param string $query Search query (natural language)
     * @param int    $limit Max results (default 5)
     *
     * @return array<int, array{id: string, score: float, snippet: string, metadata: array<string, mixed>}>
     */
    public function __invoke(string $query, int $limit = 5): array
    {
        return $this->corpus->search($query, $limit);
    }
}
