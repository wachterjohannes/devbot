<?php

declare(strict_types=1);

namespace App\Tests\Unit\Memory;

use App\Memory\Model\MemoryEntry;
use App\Memory\Model\MemoryMetadata;
use App\Memory\Model\MemoryType;
use App\Memory\Store\ShortTermStore;
use PHPUnit\Framework\TestCase;

final class ShortTermStoreTest extends TestCase
{
    public function testAddAndGet(): void
    {
        $store = new ShortTermStore(maxEntries: 10);
        $entry = new MemoryEntry('st-1', 'Hello world', MemoryType::SHORT_TERM);

        $store->add($entry);

        self::assertSame(1, $store->count());
        self::assertSame('Hello world', $store->get('st-1')?->content);
    }

    public function testRingBufferEvictsOldest(): void
    {
        $store = new ShortTermStore(maxEntries: 3);

        for ($i = 1; $i <= 5; ++$i) {
            $store->add(new MemoryEntry("st-{$i}", "Entry {$i}", MemoryType::SHORT_TERM));
        }

        self::assertSame(3, $store->count());
        self::assertNull($store->get('st-1'));
        self::assertNull($store->get('st-2'));
        self::assertNotNull($store->get('st-3'));
    }

    public function testAddTurn(): void
    {
        $store = new ShortTermStore();
        $entry = $store->addTurn('user', 'What is PHP?');

        self::assertStringStartsWith('st-', $entry->id);
        self::assertStringContainsString('[user] What is PHP?', $entry->content);
    }

    public function testSearch(): void
    {
        $store = new ShortTermStore();
        $store->add(new MemoryEntry('st-1', 'PHP is great', MemoryType::SHORT_TERM));
        $store->add(new MemoryEntry('st-2', 'Python is cool', MemoryType::SHORT_TERM));

        $results = $store->search('php');
        self::assertCount(1, $results);
        self::assertSame('st-1', $results[0]->id);
    }

    public function testSerializeAndRestore(): void
    {
        $store = new ShortTermStore();
        $store->add(new MemoryEntry('st-1', 'Test', MemoryType::SHORT_TERM, new MemoryMetadata(tags: ['test'])));

        $data = $store->serialize();
        $store2 = new ShortTermStore();
        $store2->restore($data);

        self::assertSame(1, $store2->count());
        self::assertSame('Test', $store2->get('st-1')?->content);
    }
}
