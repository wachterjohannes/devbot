<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tool;

use App\Kanban\KanbanManager;
use App\Tool\Kanban\KanbanCreateCardTool;
use App\Tool\Kanban\KanbanListTool;
use App\Tool\Kanban\KanbanMoveCardTool;
use App\Tool\Kanban\KanbanUpdateCardTool;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: tools → KanbanManager → JSON file round-trip.
 */
final class KanbanToolsTest extends TestCase
{
    private string $tmpFile;
    private KanbanManager $manager;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/devbot_kanban_int_' . bin2hex(random_bytes(4)) . '.json';
        $this->manager = new KanbanManager($this->tmpFile);
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testFullWorkflow(): void
    {
        $createTool = new KanbanCreateCardTool($this->manager);
        $listTool = new KanbanListTool($this->manager);
        $moveTool = new KanbanMoveCardTool($this->manager);
        $updateTool = new KanbanUpdateCardTool($this->manager);

        // Create a card
        $result = $createTool('Implement feature X', 'Full description', 'todo', ['feature'], 'devbot', 'high');
        self::assertArrayHasKey('id', $result);
        self::assertSame('todo', $result['status']);
        $cardId = $result['id'];

        // List board — card should be in todo
        $board = $listTool();
        self::assertCount(1, $board['todo']['cards']);
        self::assertSame('Implement feature X', $board['todo']['cards'][0]['title']);

        // Move to in_progress
        $moveResult = $moveTool($cardId, 'in_progress');
        self::assertTrue($moveResult['moved']);

        // Update priority
        $updateResult = $updateTool($cardId, priority: 'critical');
        self::assertTrue($updateResult['updated']);
        self::assertSame('critical', $updateResult['card']['priority']);

        // Move to done
        $moveTool($cardId, 'done');
        $board = $listTool();
        self::assertCount(0, $board['in_progress']['cards']);
        self::assertCount(1, $board['done']['cards']);
    }

    public function testMoveInvalidStatus(): void
    {
        $createTool = new KanbanCreateCardTool($this->manager);
        $moveTool = new KanbanMoveCardTool($this->manager);

        $result = $createTool('Test');
        $moveResult = $moveTool($result['id'], 'invalid_column');

        self::assertFalse($moveResult['moved']);
        self::assertStringContainsString('Invalid status', $moveResult['reason'] ?? '');
    }

    public function testUpdateNonexistent(): void
    {
        $updateTool = new KanbanUpdateCardTool($this->manager);
        $result = $updateTool('nonexistent-id', title: 'New title');

        self::assertFalse($result['updated']);
    }
}
