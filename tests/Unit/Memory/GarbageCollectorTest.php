<?php

declare(strict_types=1);

namespace App\Tests\Unit\Memory;

use App\Memory\Lifecycle\GarbageCollector;
use App\Memory\MemoryManager;
use App\Memory\Model\MemoryEntry;
use App\Memory\Model\MemoryMetadata;
use App\Memory\Model\MemoryType;
use App\Memory\Store\EpisodicStore;
use App\Memory\Store\LongTermStore;
use App\Memory\Store\SemanticStore;
use App\Memory\Store\ShortTermStore;
use PHPUnit\Framework\TestCase;

final class GarbageCollectorTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/devbot_gc_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/lt', 0755, true);
        mkdir($this->tmpDir . '/ep', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testRemovesLowImportanceOldEntries(): void
    {
        $longTerm = new LongTermStore($this->tmpDir . '/lt');
        $episodic = new EpisodicStore($this->tmpDir . '/ep');
        $semantic = $this->createMock(SemanticStore::class);

        $manager = new MemoryManager(new ShortTermStore(), $longTerm, $episodic, $semantic);

        // Add a low-importance entry with old last access
        $oldMeta = new MemoryMetadata(
            importance: 0.15,
            lastAccessedAt: new \DateTimeImmutable('-60 days'),
            accessCount: 0,
        );
        $entry = new MemoryEntry('lt-old', 'Old unimportant fact', MemoryType::LONG_TERM, $oldMeta);
        $longTerm->add($entry);

        $gc = new GarbageCollector($longTerm, $episodic, $manager, decayRate: 0.01, minImportance: 0.2);
        $result = $gc->collect();

        self::assertSame(1, $result['long_term_removed']);
        self::assertNull($longTerm->get('lt-old'));
    }

    public function testKeepsHighImportanceEntries(): void
    {
        $longTerm = new LongTermStore($this->tmpDir . '/lt');
        $episodic = new EpisodicStore($this->tmpDir . '/ep');
        $semantic = $this->createMock(SemanticStore::class);

        $manager = new MemoryManager(new ShortTermStore(), $longTerm, $episodic, $semantic);

        $meta = new MemoryMetadata(importance: 0.9, accessCount: 5);
        $entry = new MemoryEntry('lt-important', 'Critical decision', MemoryType::LONG_TERM, $meta);
        $longTerm->add($entry);

        $gc = new GarbageCollector($longTerm, $episodic, $manager);
        $result = $gc->collect();

        self::assertSame(0, $result['long_term_removed']);
        self::assertNotNull($longTerm->get('lt-important'));
    }

    public function testAccessBoostResistsDecay(): void
    {
        $longTerm = new LongTermStore($this->tmpDir . '/lt');
        $episodic = new EpisodicStore($this->tmpDir . '/ep');
        $semantic = $this->createMock(SemanticStore::class);

        $manager = new MemoryManager(new ShortTermStore(), $longTerm, $episodic, $semantic);

        // Low importance but frequently accessed
        $meta = new MemoryMetadata(
            importance: 0.25,
            lastAccessedAt: new \DateTimeImmutable('-5 days'),
            accessCount: 10,
        );
        $entry = new MemoryEntry('lt-accessed', 'Frequently used', MemoryType::LONG_TERM, $meta);
        $longTerm->add($entry);

        $gc = new GarbageCollector($longTerm, $episodic, $manager, decayRate: 0.01, minImportance: 0.2);
        $result = $gc->collect();

        // 0.25 - (5 * 0.01) + min(0.2, 10 * 0.02) = 0.25 - 0.05 + 0.2 = 0.40 > 0.2
        self::assertSame(0, $result['long_term_removed']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
