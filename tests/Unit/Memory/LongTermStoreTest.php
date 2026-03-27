<?php

declare(strict_types=1);

namespace App\Tests\Unit\Memory;

use App\Memory\Model\MemoryEntry;
use App\Memory\Model\MemoryMetadata;
use App\Memory\Model\MemoryType;
use App\Memory\Store\LongTermStore;
use PHPUnit\Framework\TestCase;

final class LongTermStoreTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/devbot_lt_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testAddAndGet(): void
    {
        $store = new LongTermStore($this->tmpDir);
        $entry = new MemoryEntry(
            'lt-1',
            'Symfony uses PSR-12',
            MemoryType::LONG_TERM,
            new MemoryMetadata(tags: ['coding'], topic: 'patterns'),
        );

        $store->add($entry);
        $retrieved = $store->get('lt-1');

        self::assertNotNull($retrieved);
        self::assertSame('Symfony uses PSR-12', $retrieved->content);
        self::assertSame('patterns', $retrieved->metadata->topic);
    }

    public function testUpdate(): void
    {
        $store = new LongTermStore($this->tmpDir);
        $store->add(new MemoryEntry('lt-1', 'Original', MemoryType::LONG_TERM));

        $store->update('lt-1', 'Updated content');
        $entry = $store->get('lt-1');

        self::assertSame('Updated content', $entry?->content);
    }

    public function testRemove(): void
    {
        $store = new LongTermStore($this->tmpDir);
        $store->add(new MemoryEntry('lt-1', 'To remove', MemoryType::LONG_TERM));

        self::assertTrue($store->remove('lt-1'));
        self::assertNull($store->get('lt-1'));
        self::assertFalse($store->remove('nonexistent'));
    }

    public function testListByTopic(): void
    {
        $store = new LongTermStore($this->tmpDir);
        $store->add(new MemoryEntry('lt-1', 'A', MemoryType::LONG_TERM, new MemoryMetadata(topic: 'projects')));
        $store->add(new MemoryEntry('lt-2', 'B', MemoryType::LONG_TERM, new MemoryMetadata(topic: 'decisions')));

        $projects = $store->list('projects');
        self::assertCount(1, $projects);
        self::assertSame('lt-1', $projects[0]->id);
    }

    public function testGrep(): void
    {
        $store = new LongTermStore($this->tmpDir);
        $store->add(new MemoryEntry('lt-1', "Line one\nPHP is great\nLine three", MemoryType::LONG_TERM));
        $store->add(new MemoryEntry('lt-2', 'No match here', MemoryType::LONG_TERM));

        $results = $store->grep('PHP');
        self::assertCount(1, $results);
        self::assertSame('lt-1', $results[0]['entry']->id);
        self::assertSame(2, $results[0]['lineNumber']);
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
