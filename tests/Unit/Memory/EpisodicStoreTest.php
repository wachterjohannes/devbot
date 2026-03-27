<?php

declare(strict_types=1);

namespace App\Tests\Unit\Memory;

use App\Memory\Model\MemoryEntry;
use App\Memory\Model\MemoryMetadata;
use App\Memory\Model\MemoryType;
use App\Memory\Store\EpisodicStore;
use PHPUnit\Framework\TestCase;

final class EpisodicStoreTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/devbot_ep_test_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testAddCreatesDateDirectory(): void
    {
        $store = new EpisodicStore($this->tmpDir);
        $entry = new MemoryEntry('ep-1', 'Something happened', MemoryType::EPISODIC, new MemoryMetadata(tags: ['task']));

        $store->add($entry);

        $date = $entry->metadata->createdAt->format('Y-m-d');
        self::assertDirectoryExists($this->tmpDir . '/' . $date);
    }

    public function testGetById(): void
    {
        $store = new EpisodicStore($this->tmpDir);
        $entry = new MemoryEntry('ep-1', 'Event content', MemoryType::EPISODIC);
        $store->add($entry);

        $found = $store->get('ep-1');
        self::assertNotNull($found);
        self::assertSame('Event content', $found->content);
    }

    public function testGetByDate(): void
    {
        $store = new EpisodicStore($this->tmpDir);
        $entry = new MemoryEntry('ep-1', 'Today event', MemoryType::EPISODIC);
        $store->add($entry);

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $events = $store->getByDate($today);

        self::assertCount(1, $events);
        self::assertSame('ep-1', $events[0]->id);
    }

    public function testGetByDateReturnsEmptyForMissingDate(): void
    {
        $store = new EpisodicStore($this->tmpDir);
        self::assertSame([], $store->getByDate('1999-01-01'));
    }

    public function testGetRecent(): void
    {
        $store = new EpisodicStore($this->tmpDir);

        for ($i = 1; $i <= 5; ++$i) {
            $store->add(new MemoryEntry("ep-{$i}", "Event {$i}", MemoryType::EPISODIC));
        }

        $recent = $store->getRecent(3);
        self::assertCount(3, $recent);
    }

    public function testSearch(): void
    {
        $store = new EpisodicStore($this->tmpDir);
        $store->add(new MemoryEntry('ep-1', 'Deployed to production', MemoryType::EPISODIC));
        $store->add(new MemoryEntry('ep-2', 'Fixed a typo', MemoryType::EPISODIC));

        $results = $store->search('production');
        self::assertCount(1, $results);
        self::assertSame('ep-1', $results[0]->id);
    }

    public function testPurgeOlderThan(): void
    {
        $store = new EpisodicStore($this->tmpDir);

        // Create an entry with a fake old date by writing directly
        $oldDate = (new \DateTimeImmutable('-100 days'))->format('Y-m-d');
        $dir = $this->tmpDir . '/' . $oldDate;
        mkdir($dir, 0755, true);
        $entry = new MemoryEntry('ep-old', 'Old event', MemoryType::EPISODIC);
        file_put_contents($dir . '/120000-event.json', json_encode($entry->jsonSerialize()));

        // Create a recent entry
        $store->add(new MemoryEntry('ep-new', 'Recent event', MemoryType::EPISODIC));

        $removed = $store->purgeOlderThan(90);
        self::assertSame(1, $removed);
        self::assertNull($store->get('ep-old'));
        self::assertNotNull($store->get('ep-new'));
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
