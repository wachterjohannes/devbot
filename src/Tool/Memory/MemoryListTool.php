<?php

declare(strict_types=1);

namespace App\Tool\Memory;

use App\Memory\MemoryManager;
use App\Memory\Model\MemoryType;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * List memory entries, optionally filtered by type, tags, or topic.
 */
#[AsTool('memory_list', 'List memory entries. Filter by type, topic, or tags to browse stored knowledge.')]
final readonly class MemoryListTool
{
    public function __construct(
        private MemoryManager $memoryManager,
    ) {
    }

    /**
     * @param string|null       $type   Memory type filter: "short_term", "long_term", "episodic"
     * @param string|null       $topic  Topic filter (e.g. "projects", "decisions")
     * @param list<string>|null $tags   Tag filter (entries matching any tag)
     * @param int               $limit  Max results (default 20)
     *
     * @return array<int, array{id: string, type: string, snippet: string, tags: list<string>, importance: float}>
     */
    public function __invoke(
        ?string $type = null,
        ?string $topic = null,
        ?array $tags = null,
        int $limit = 20,
    ): array {
        $memoryType = $type !== null ? MemoryType::tryFrom($type) : null;
        $entries = $this->memoryManager->list($memoryType, $topic, $tags, $limit);

        return array_map(
            static fn ($entry) => [
                'id' => $entry->id,
                'type' => $entry->type->value,
                'snippet' => $entry->getSnippet(150),
                'tags' => $entry->metadata->tags,
                'importance' => $entry->metadata->importance,
            ],
            $entries,
        );
    }
}
