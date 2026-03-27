<?php

declare(strict_types=1);

namespace App\Tool\Memory;

use App\Memory\Search\MemoryCorpus;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Read full content of a memory entry by ID and add it to the working set.
 * Use after search/grep to investigate an entry more deeply.
 */
#[AsTool('memory_read', 'Read full content of a memory entry by ID. Adds it to the working set for context.')]
final readonly class MemoryReadTool
{
    public function __construct(
        private MemoryCorpus $corpus,
    ) {
    }

    /**
     * @param string $id The memory entry ID
     *
     * @return array{id: string, content: string, type: string, tags: list<string>, importance: float}|array{error: string}
     */
    public function __invoke(string $id): array
    {
        $entry = $this->corpus->read($id);

        if ($entry === null) {
            return ['error' => "Memory entry '{$id}' not found or has been pruned."];
        }

        return [
            'id' => $entry->id,
            'content' => $entry->content,
            'type' => $entry->type->value,
            'tags' => $entry->metadata->tags,
            'importance' => $entry->metadata->importance,
        ];
    }
}
