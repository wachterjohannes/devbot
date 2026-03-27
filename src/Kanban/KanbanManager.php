<?php

declare(strict_types=1);

namespace App\Kanban;

use App\Kanban\Model\Board;
use App\Kanban\Model\Card;
use App\Kanban\Model\CardStatus;

/**
 * Manages the kanban board: CRUD on cards, persistence to JSON file.
 */
final class KanbanManager
{
    private ?Board $board = null;

    public function __construct(
        private readonly string $boardFile,
    ) {
    }

    public function getBoard(): Board
    {
        if ($this->board === null) {
            $this->board = $this->load();
        }

        return $this->board;
    }

    /**
     * Create a new card and add it to the board.
     *
     * @param list<string> $labels
     */
    public function createCard(
        string $title,
        string $description = '',
        CardStatus $status = CardStatus::BACKLOG,
        array $labels = [],
        ?string $assignee = null,
        string $priority = 'medium',
    ): Card {
        $card = new Card(
            id: 'card-' . bin2hex(random_bytes(6)),
            title: $title,
            description: $description,
            status: $status,
            labels: $labels,
            assignee: $assignee,
            priority: $priority,
        );

        $this->getBoard()->addCard($card);
        $this->save();

        return $card;
    }

    /**
     * Move a card to a different column.
     *
     * @return array{moved: bool, reason?: string}
     */
    public function moveCard(string $cardId, CardStatus $targetStatus): array
    {
        $board = $this->getBoard();
        $card = $board->getCard($cardId);

        if ($card === null) {
            return ['moved' => false, 'reason' => "Card '{$cardId}' not found."];
        }

        if ($card->status === $targetStatus) {
            return ['moved' => false, 'reason' => "Card is already in '{$targetStatus->value}'."];
        }

        if ($board->wouldExceedWipLimit($targetStatus)) {
            return ['moved' => false, 'reason' => "WIP limit reached for '{$targetStatus->value}'."];
        }

        $card->moveTo($targetStatus);
        $this->save();

        return ['moved' => true];
    }

    /**
     * Update card fields.
     *
     * @param list<string>|null $labels
     */
    public function updateCard(
        string $cardId,
        ?string $title = null,
        ?string $description = null,
        ?array $labels = null,
        ?string $assignee = null,
        ?string $priority = null,
    ): ?Card {
        $card = $this->getBoard()->getCard($cardId);

        if ($card === null) {
            return null;
        }

        if ($title !== null) {
            $card->title = $title;
        }
        if ($description !== null) {
            $card->description = $description;
        }
        if ($labels !== null) {
            $card->labels = $labels;
        }
        if ($assignee !== null) {
            $card->assignee = $assignee;
        }
        if ($priority !== null) {
            $card->priority = $priority;
        }

        $card->updatedAt = new \DateTimeImmutable();
        $this->save();

        return $card;
    }

    /**
     * Remove a card from the board.
     */
    public function removeCard(string $cardId): bool
    {
        $removed = $this->getBoard()->removeCard($cardId);

        if ($removed) {
            $this->save();
        }

        return $removed;
    }

    /**
     * Get a summary of the board state.
     *
     * @return array<string, array{name: string, wip_limit: int|null, cards: list<array<string, mixed>>}>
     */
    public function getBoardSummary(): array
    {
        $board = $this->getBoard();
        $summary = [];

        foreach ($board->columns as $column) {
            $cards = $board->getCardsByStatus($column->status);
            $summary[$column->status->value] = [
                'name' => $column->name,
                'wip_limit' => $column->wipLimit,
                'cards' => array_map(static fn (Card $c) => [
                    'id' => $c->id,
                    'title' => $c->title,
                    'priority' => $c->priority,
                    'assignee' => $c->assignee,
                    'labels' => $c->labels,
                ], $cards),
            ];
        }

        return $summary;
    }

    private function load(): Board
    {
        if (!is_file($this->boardFile)) {
            return new Board();
        }

        $json = file_get_contents($this->boardFile);
        if ($json === false || $json === '') {
            return new Board();
        }

        $data = json_decode($json, true);

        return \is_array($data) ? Board::fromArray($data) : new Board();
    }

    private function save(): void
    {
        $dir = \dirname($this->boardFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->boardFile,
            json_encode($this->getBoard()->jsonSerialize(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
        );
    }
}
