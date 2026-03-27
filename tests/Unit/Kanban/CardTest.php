<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kanban;

use App\Kanban\Model\Card;
use App\Kanban\Model\CardStatus;
use PHPUnit\Framework\TestCase;

final class CardTest extends TestCase
{
    public function testSerializationRoundTrip(): void
    {
        $card = new Card(
            id: 'card-abc',
            title: 'Test card',
            description: 'A description',
            status: CardStatus::IN_PROGRESS,
            labels: ['bug', 'urgent'],
            assignee: 'devbot',
            priority: 'high',
            subtasks: [['title' => 'Sub 1', 'done' => false]],
        );

        $data = $card->jsonSerialize();
        $restored = Card::fromArray($data);

        self::assertSame('card-abc', $restored->id);
        self::assertSame('Test card', $restored->title);
        self::assertSame('A description', $restored->description);
        self::assertSame(CardStatus::IN_PROGRESS, $restored->status);
        self::assertSame(['bug', 'urgent'], $restored->labels);
        self::assertSame('devbot', $restored->assignee);
        self::assertSame('high', $restored->priority);
        self::assertCount(1, $restored->subtasks);
    }

    public function testMoveTo(): void
    {
        $card = new Card('card-1', 'Task');
        $beforeUpdate = $card->updatedAt;

        $card->moveTo(CardStatus::DONE);

        self::assertSame(CardStatus::DONE, $card->status);
        self::assertGreaterThanOrEqual($beforeUpdate, $card->updatedAt);
    }

    public function testFromArrayWithDefaults(): void
    {
        $card = Card::fromArray(['id' => 'card-x', 'title' => 'Minimal']);

        self::assertSame(CardStatus::BACKLOG, $card->status);
        self::assertSame([], $card->labels);
        self::assertNull($card->assignee);
        self::assertSame('medium', $card->priority);
    }
}
