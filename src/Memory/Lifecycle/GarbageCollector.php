<?php

declare(strict_types=1);

namespace App\Memory\Lifecycle;

use App\Memory\MemoryManager;
use App\Memory\Model\MemoryEntry;
use App\Memory\Model\MemoryType;
use App\Memory\Store\EpisodicStore;
use App\Memory\Store\LongTermStore;

/**
 * Removes stale and low-importance memories.
 * Applies time-based decay: older entries need higher importance to survive.
 */
final readonly class GarbageCollector
{
    public function __construct(
        private LongTermStore $longTermStore,
        private EpisodicStore $episodicStore,
        private MemoryManager $memoryManager,
        private int $episodicRetentionDays = 90,
        private float $decayRate = 0.01,
        private float $minImportance = 0.2,
    ) {
    }

    /**
     * Run garbage collection across all memory tiers.
     *
     * @return array{long_term_removed: int, episodic_purged: int}
     */
    public function collect(): array
    {
        $ltRemoved = $this->collectLongTerm();
        $epPurged = $this->episodicStore->purgeOlderThan($this->episodicRetentionDays);

        return [
            'long_term_removed' => $ltRemoved,
            'episodic_purged' => $epPurged,
        ];
    }

    private function collectLongTerm(): int
    {
        $removed = 0;
        $now = new \DateTimeImmutable();

        foreach ($this->longTermStore->getAllIds() as $id) {
            $entry = $this->longTermStore->get($id);

            if ($entry === null) {
                continue;
            }

            $effectiveImportance = $this->calculateEffectiveImportance($entry, $now);

            if ($effectiveImportance < $this->minImportance) {
                $this->memoryManager->remove($id);
                ++$removed;
            }
        }

        return $removed;
    }

    /**
     * Importance decays over time but is boosted by access frequency.
     */
    private function calculateEffectiveImportance(MemoryEntry $entry, \DateTimeImmutable $now): float
    {
        $meta = $entry->metadata;
        $daysSinceAccess = max(0, (int) $now->diff($meta->lastAccessedAt)->days);

        // Decay: importance drops over time since last access
        $decayed = $meta->importance - ($daysSinceAccess * $this->decayRate);

        // Access boost: frequently accessed entries resist decay
        $accessBoost = min(0.2, $meta->accessCount * 0.02);

        return max(0.0, $decayed + $accessBoost);
    }
}
