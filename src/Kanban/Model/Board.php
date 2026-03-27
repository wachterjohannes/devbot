<?php

declare(strict_types=1);

namespace App\Kanban\Model;

/**
 * The kanban board: columns with WIP limits and cards.
 */
final class Board implements \JsonSerializable
{
    /** @var list<Column> */
    public readonly array $columns;

    /** @var array<string, Card> */
    private array $cards = [];

    public function __construct()
    {
        $this->columns = [
            new Column(CardStatus::BACKLOG, 'Backlog'),
            new Column(CardStatus::TODO, 'To Do', wipLimit: 5),
            new Column(CardStatus::IN_PROGRESS, 'In Progress', wipLimit: 3),
            new Column(CardStatus::REVIEW, 'Review', wipLimit: 2),
            new Column(CardStatus::DONE, 'Done'),
        ];
    }

    public function addCard(Card $card): void
    {
        $this->cards[$card->id] = $card;
    }

    public function getCard(string $id): ?Card
    {
        return $this->cards[$id] ?? null;
    }

    public function removeCard(string $id): bool
    {
        if (!isset($this->cards[$id])) {
            return false;
        }

        unset($this->cards[$id]);

        return true;
    }

    /**
     * @return list<Card>
     */
    public function getCardsByStatus(CardStatus $status): array
    {
        return array_values(array_filter(
            $this->cards,
            static fn (Card $c) => $c->status === $status,
        ));
    }

    /**
     * @return array<string, Card>
     */
    public function getAllCards(): array
    {
        return $this->cards;
    }

    /**
     * Check if moving a card to a column would violate WIP limits.
     */
    public function wouldExceedWipLimit(CardStatus $targetStatus): bool
    {
        foreach ($this->columns as $column) {
            if ($column->status === $targetStatus && $column->wipLimit !== null) {
                $currentCount = \count($this->getCardsByStatus($targetStatus));

                return $currentCount >= $column->wipLimit;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'columns' => array_map(
                static fn (Column $c) => $c->jsonSerialize(),
                $this->columns,
            ),
            'cards' => array_map(
                static fn (Card $c) => $c->jsonSerialize(),
                array_values($this->cards),
            ),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $board = new self();

        foreach ($data['cards'] ?? [] as $cardData) {
            $board->addCard(Card::fromArray($cardData));
        }

        return $board;
    }
}
