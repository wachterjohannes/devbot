<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tool;

use App\Memory\MemoryManager;
use App\Memory\Search\MemoryCorpus;
use App\Memory\Store\EpisodicStore;
use App\Memory\Store\LongTermStore;
use App\Memory\Store\SemanticStore;
use App\Memory\Store\ShortTermStore;
use App\Tool\Memory\MemoryAddTool;
use App\Tool\Memory\MemoryGrepTool;
use App\Tool\Memory\MemoryListTool;
use App\Tool\Memory\MemoryPruneTool;
use App\Tool\Memory\MemoryReadTool;
use App\Tool\Memory\MemoryRemoveTool;
use App\Tool\Memory\MemoryUpdateTool;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: memory tools → MemoryManager → stores round-trip.
 * SemanticStore is mocked (needs Ollama for embeddings).
 */
final class MemoryToolsTest extends TestCase
{
    private string $tmpDir;
    private MemoryManager $manager;
    private MemoryCorpus $corpus;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/devbot_memtools_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/lt', 0755, true);
        mkdir($this->tmpDir . '/ep', 0755, true);

        $semantic = $this->createMock(SemanticStore::class);

        $this->manager = new MemoryManager(
            new ShortTermStore(),
            new LongTermStore($this->tmpDir . '/lt'),
            new EpisodicStore($this->tmpDir . '/ep'),
            $semantic,
        );

        $this->corpus = new MemoryCorpus($this->manager);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testAddAndListLongTerm(): void
    {
        $addTool = new MemoryAddTool($this->manager);
        $listTool = new MemoryListTool($this->manager);

        $result = $addTool('We decided to use PostgreSQL', 'long_term', ['database', 'decision'], 0.8, 'decisions');
        self::assertTrue($result['stored']);
        self::assertSame('long_term', $result['type']);

        $entries = $listTool('long_term', 'decisions');
        self::assertCount(1, $entries);
        self::assertStringContainsString('PostgreSQL', $entries[0]['snippet']);
        self::assertSame(['database', 'decision'], $entries[0]['tags']);
    }

    public function testAddEpisodic(): void
    {
        $addTool = new MemoryAddTool($this->manager);
        $listTool = new MemoryListTool($this->manager);

        $result = $addTool('Deployed v2.0 to production', 'episodic', ['deploy']);
        self::assertSame('episodic', $result['type']);

        $entries = $listTool('episodic');
        self::assertCount(1, $entries);
    }

    public function testUpdateAndRead(): void
    {
        $addTool = new MemoryAddTool($this->manager);
        $updateTool = new MemoryUpdateTool($this->manager);
        $readTool = new MemoryReadTool($this->corpus);

        $result = $addTool('Original content', 'long_term', ['test']);
        $id = $result['id'];

        $updateTool($id, content: 'Updated content', tags: ['test', 'updated']);

        $read = $readTool($id);
        self::assertSame('Updated content', $read['content']);
        self::assertSame(['test', 'updated'], $read['tags']);
    }

    public function testRemove(): void
    {
        $addTool = new MemoryAddTool($this->manager);
        $removeTool = new MemoryRemoveTool($this->manager);
        $readTool = new MemoryReadTool($this->corpus);

        $result = $addTool('To be deleted', 'long_term');
        $id = $result['id'];

        $removeResult = $removeTool($id);
        self::assertTrue($removeResult['removed']);

        $read = $readTool($id);
        self::assertArrayHasKey('error', $read);
    }

    public function testGrepAndPrune(): void
    {
        $addTool = new MemoryAddTool($this->manager);
        $grepTool = new MemoryGrepTool($this->corpus);
        $pruneTool = new MemoryPruneTool($this->corpus);

        $addTool('Symfony uses PSR-12 coding standard', 'long_term', ['coding']);
        $addTool('React uses JSX syntax', 'long_term', ['frontend']);

        // Grep finds Symfony entry
        $results = $grepTool('PSR-12');
        self::assertCount(1, $results);
        $id = $results[0]['id'];

        // Prune it
        $pruneResult = $pruneTool($id);
        self::assertSame($id, $pruneResult['pruned']);

        // Grep no longer returns it
        $results2 = $grepTool('PSR-12');
        self::assertCount(0, $results2);
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
