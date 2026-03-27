<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kanban;

use App\Kanban\KanbanManager;
use App\Kanban\Model\CardStatus;
use PHPUnit\Framework\TestCase;

final class KanbanManagerTest extends TestCase
{
    private string $tmpFile;
    private KanbanManager $manager;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/devbot_board_test_' . bin2hex(random_bytes(4)) . '.json';
        $this->manager = new KanbanManager($this->tmpFile);
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testCreateCard(): void
    {
        $card = $this->manager->createCard('Test task', 'Description', labels: ['test']);

        self::assertStringStartsWith('card-', $card->id);
        self::assertSame('Test task', $card->title);
        self::assertSame(CardStatus::BACKLOG, $card->status);
        self::assertSame(['test'], $card->labels);
        self::assertFileExists($this->tmpFile);
    }

    public function testMoveCard(): void
    {
        $card = $this->manager->createCard('Move me');
        $result = $this->manager->moveCard($card->id, CardStatus::IN_PROGRESS);

        self::assertTrue($result['moved']);
        self::assertSame(CardStatus::IN_PROGRESS, $this->manager->getBoard()->getCard($card->id)?->status);
    }

    public function testMoveCardRespectsWipLimit(): void
    {
        // In Progress has WIP limit of 3
        for ($i = 0; $i < 3; ++$i) {
            $this->manager->createCard("Task {$i}", status: CardStatus::IN_PROGRESS);
        }

        $card = $this->manager->createCard('One too many');
        $result = $this->manager->moveCard($card->id, CardStatus::IN_PROGRESS);

        self::assertFalse($result['moved']);
        self::assertStringContainsString('WIP limit', $result['reason'] ?? '');
    }

    public function testUpdateCard(): void
    {
        $card = $this->manager->createCard('Original');
        $updated = $this->manager->updateCard($card->id, title: 'Updated', priority: 'high');

        self::assertNotNull($updated);
        self::assertSame('Updated', $updated->title);
        self::assertSame('high', $updated->priority);
    }

    public function testRemoveCard(): void
    {
        $card = $this->manager->createCard('To delete');

        self::assertTrue($this->manager->removeCard($card->id));
        self::assertNull($this->manager->getBoard()->getCard($card->id));
        self::assertFalse($this->manager->removeCard('nonexistent'));
    }

    public function testBoardSummary(): void
    {
        $this->manager->createCard('Task A', status: CardStatus::TODO);
        $this->manager->createCard('Task B', status: CardStatus::IN_PROGRESS);

        $summary = $this->manager->getBoardSummary();

        self::assertCount(1, $summary['todo']['cards']);
        self::assertCount(1, $summary['in_progress']['cards']);
        self::assertCount(0, $summary['backlog']['cards']);
    }

    public function testPersistenceAcrossInstances(): void
    {
        $this->manager->createCard('Persistent');

        $manager2 = new KanbanManager($this->tmpFile);
        $cards = $manager2->getBoard()->getAllCards();

        self::assertCount(1, $cards);
        self::assertSame('Persistent', array_values($cards)[0]->title);
    }
}
