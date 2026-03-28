<?php

declare(strict_types=1);

namespace App\Memory\Lifecycle;

use App\Memory\MemoryManager;
use App\Memory\Model\MemoryType;
use App\Memory\Store\ShortTermStore;
use App\Memory\Strategy\RuleBasedImportanceScorer;

/**
 * Runs at session end: extracts learnings from the short-term conversation
 * buffer and persists important ones to long-term and episodic memory.
 */
final readonly class SessionEndHandler
{
    public function __construct(
        private MemoryManager $memoryManager,
        private ShortTermStore $shortTermStore,
        private RuleBasedImportanceScorer $importanceScorer,
        private float $importanceThreshold = 0.4,
    ) {
    }

    /**
     * Process session end: score short-term entries, persist important ones.
     *
     * @return int Number of entries persisted
     */
    public function handle(): int
    {
        $entries = $this->shortTermStore->getAll();
        $persisted = 0;

        foreach ($entries as $entry) {
            $score = $this->importanceScorer->score($entry->content);

            if ($score < $this->importanceThreshold) {
                continue;
            }

            // Skip very short entries (greetings, acks)
            if (mb_strlen($entry->content) < 30) {
                continue;
            }

            // Determine target type based on content
            if ($this->isEvent($entry->content)) {
                $this->memoryManager->logEvent(
                    $entry->content,
                    tags: ['session', 'auto-extracted'],
                    importance: $score,
                    source: 'session-end',
                );
            } else {
                $this->memoryManager->remember(
                    $entry->content,
                    topic: $this->inferTopic($entry->content),
                    tags: ['session', 'auto-extracted'],
                    importance: $score,
                    source: 'session-end',
                );
            }

            ++$persisted;
        }

        // Log the session itself as an episodic event
        if ($persisted > 0) {
            $this->memoryManager->logEvent(
                "Session ended. Extracted {$persisted} learnings from " . \count($entries) . ' conversation turns.',
                tags: ['session', 'lifecycle'],
                importance: 0.3,
            );
        }

        return $persisted;
    }

    private function isEvent(string $content): bool
    {
        $eventKeywords = ['deployed', 'completed', 'finished', 'created', 'merged', 'released', 'fixed', 'resolved'];
        $lower = mb_strtolower($content);

        foreach ($eventKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function inferTopic(string $content): string
    {
        $lower = mb_strtolower($content);

        $topicMap = [
            'decision' => 'decisions',
            'decided' => 'decisions',
            'architecture' => 'decisions',
            'pattern' => 'patterns',
            'convention' => 'patterns',
            'always' => 'patterns',
            'never' => 'patterns',
            'prefer' => 'preferences',
            'preference' => 'preferences',
            'project' => 'projects',
        ];

        foreach ($topicMap as $keyword => $topic) {
            if (str_contains($lower, $keyword)) {
                return $topic;
            }
        }

        return 'general';
    }
}
