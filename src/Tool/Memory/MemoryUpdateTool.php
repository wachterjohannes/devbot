<?php

declare(strict_types=1);

namespace App\Tool\Memory;

use App\Memory\MemoryManager;
use App\Memory\Model\MemoryMetadata;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Update an existing long-term memory entry's content or metadata.
 */
#[AsTool('memory_update', 'Update a memory entry. Can change content, tags, importance, or topic.')]
final readonly class MemoryUpdateTool
{
    public function __construct(
        private MemoryManager $memoryManager,
    ) {
    }

    /**
     * @param string            $id         The memory entry ID to update
     * @param string|null       $content    New content (null to keep existing)
     * @param list<string>|null $tags       New tags (null to keep existing)
     * @param float|null        $importance New importance (null to keep existing)
     * @param string|null       $topic      New topic (null to keep existing)
     *
     * @return array{updated: bool, id: string}
     */
    public function __invoke(
        string $id,
        ?string $content = null,
        ?array $tags = null,
        ?float $importance = null,
        ?string $topic = null,
    ): array {
        $metadata = null;

        if ($tags !== null || $importance !== null || $topic !== null) {
            $existing = $this->memoryManager->get($id);

            if ($existing === null) {
                return ['updated' => false, 'id' => $id];
            }

            $metadata = $existing->metadata;

            if ($tags !== null) {
                $metadata->tags = $tags;
            }

            if ($importance !== null) {
                $metadata->importance = $importance;
            }

            if ($topic !== null) {
                $metadata->topic = $topic;
            }
        }

        $updated = $this->memoryManager->update($id, $content, $metadata);

        return [
            'updated' => $updated,
            'id' => $id,
        ];
    }
}
