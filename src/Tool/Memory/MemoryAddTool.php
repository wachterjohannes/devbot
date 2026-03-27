<?php

declare(strict_types=1);

namespace App\Tool\Memory;

use App\Memory\MemoryManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Add a new memory entry. Routes to the appropriate store based on type.
 */
#[AsTool('memory_add', 'Store a new memory entry. Use for facts, decisions, patterns, or events worth remembering.')]
final readonly class MemoryAddTool
{
    public function __construct(
        private MemoryManager $memoryManager,
    ) {
    }

    /**
     * @param string       $content    The memory content to store
     * @param string       $type       Memory type: "long_term" or "episodic"
     * @param list<string> $tags       Tags for categorization
     * @param float        $importance Importance score 0.0-1.0 (default 0.5)
     * @param string       $topic      Topic category (e.g. "projects", "decisions", "patterns")
     *
     * @return array{id: string, type: string, stored: true}
     */
    public function __invoke(
        string $content,
        string $type = 'long_term',
        array $tags = [],
        float $importance = 0.5,
        string $topic = 'general',
    ): array {
        if ($type === 'episodic') {
            $entry = $this->memoryManager->logEvent($content, $tags, $importance);
        } else {
            $entry = $this->memoryManager->remember($content, $topic, $tags, $importance);
        }

        return [
            'id' => $entry->id,
            'type' => $entry->type->value,
            'stored' => true,
        ];
    }
}
