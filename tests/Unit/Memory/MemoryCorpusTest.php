<?php

declare(strict_types=1);

namespace App\Tests\Unit\Memory;

use App\Memory\MemoryManager;
use App\Memory\Model\MemoryEntry;
use App\Memory\Model\MemoryMetadata;
use App\Memory\Model\MemoryType;
use App\Memory\Search\MemoryCorpus;
use App\Memory\Store\EpisodicStore;
use App\Memory\Store\LongTermStore;
use App\Memory\Store\SemanticStore;
use App\Memory\Store\ShortTermStore;
use PHPUnit\Framework\TestCase;

final class MemoryCorpusTest extends TestCase
{
    private string $tmpDir;
    private MemoryManager $manager;
    private MemoryCorpus $corpus;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/devbot_corpus_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/lt', 0755, true);
        mkdir($this->tmpDir . '/ep', 0755, true);

        $shortTerm = new ShortTermStore();
        $longTerm = new LongTermStore($this->tmpDir . '/lt');
        $episodic = new EpisodicStore($this->tmpDir . '/ep');
        $semantic = $this->createMock(SemanticStore::class);

        $this->manager = new MemoryManager($shortTerm, $longTerm, $episodic, $semantic);
        $this->corpus = new MemoryCorpus($this->manager);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testGrepFindsEntries(): void
    {
        $this->manager->remember('PHP is a great language', 'patterns', ['php']);
        $this->manager->remember('Python is also nice', 'patterns', ['python']);

        $results = $this->corpus->grep('PHP');
        self::assertNotEmpty($results);
        self::assertStringContainsString('PHP', $results[0]['line']);
    }

    public function testGrepExcludesPrunedEntries(): void
    {
        $entry = $this->manager->remember('Secret PHP fact', 'patterns');

        // Grep finds it
        $results = $this->corpus->grep('PHP');
        self::assertNotEmpty($results);

        // Prune it
        $this->corpus->prune($entry->id);

        // Grep no longer finds it
        $results2 = $this->corpus->grep('PHP');
        self::assertEmpty($results2);
    }

    public function testReadAddsToWorkingSet(): void
    {
        $entry = $this->manager->remember('Important decision', 'decisions', ['arch']);

        self::assertEmpty($this->corpus->getWorkingSet());

        $read = $this->corpus->read($entry->id);
        self::assertNotNull($read);
        self::assertCount(1, $this->corpus->getWorkingSet());
        self::assertSame('Important decision', $this->corpus->getWorkingSet()[$entry->id]->content);
    }

    public function testReadPrunedReturnsNull(): void
    {
        $entry = $this->manager->remember('Will be pruned', 'general');
        $this->corpus->prune($entry->id);

        self::assertNull($this->corpus->read($entry->id));
    }

    public function testPruneRemovesFromWorkingSet(): void
    {
        $entry = $this->manager->remember('Temporary', 'general');
        $this->corpus->read($entry->id);
        self::assertCount(1, $this->corpus->getWorkingSet());

        $this->corpus->prune($entry->id);
        self::assertCount(0, $this->corpus->getWorkingSet());
    }

    public function testWorkingSetSummaryEmpty(): void
    {
        $summary = $this->corpus->getWorkingSetSummary();
        self::assertStringContainsString('empty', $summary);
    }

    public function testWorkingSetSummaryWithEntries(): void
    {
        $this->manager->remember('Decision about DB', 'decisions', ['database']);
        $entries = $this->manager->list();
        $this->corpus->read($entries[0]->id);

        $summary = $this->corpus->getWorkingSetSummary();
        self::assertStringContainsString('1 entries', $summary);
        self::assertStringContainsString('Decision about DB', $summary);
    }

    public function testReset(): void
    {
        $entry = $this->manager->remember('Data', 'general');
        $this->corpus->read($entry->id);
        $this->corpus->prune('some-id');

        self::assertNotEmpty($this->corpus->getWorkingSet());

        $this->corpus->reset();
        self::assertEmpty($this->corpus->getWorkingSet());
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
