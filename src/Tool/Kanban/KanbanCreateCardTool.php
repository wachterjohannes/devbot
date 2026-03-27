<?php

declare(strict_types=1);

namespace App\Tool\Kanban;

use App\Kanban\KanbanManager;
use App\Kanban\Model\CardStatus;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Create a new card on the kanban board.
 */
#[AsTool('kanban_create_card', 'Create a new task card on the kanban board.')]
final readonly class KanbanCreateCardTool
{
    public function __construct(
        private KanbanManager $kanban,
    ) {
    }

    /**
     * @param string       $title       Card title
     * @param string       $description Card description
     * @param string       $status      Column: "backlog", "todo", "in_progress", "review", "done"
     * @param list<string> $labels      Labels for categorization
     * @param string|null  $assignee    Who is assigned (e.g. "devbot", "johannes")
     * @param string       $priority    Priority: "low", "medium", "high", "critical"
     *
     * @return array{id: string, title: string, status: string}
     */
    public function __invoke(
        string $title,
        string $description = '',
        string $status = 'backlog',
        array $labels = [],
        ?string $assignee = null,
        string $priority = 'medium',
    ): array {
        $cardStatus = CardStatus::tryFrom($status) ?? CardStatus::BACKLOG;

        $card = $this->kanban->createCard(
            title: $title,
            description: $description,
            status: $cardStatus,
            labels: $labels,
            assignee: $assignee,
            priority: $priority,
        );

        return [
            'id' => $card->id,
            'title' => $card->title,
            'status' => $card->status->value,
        ];
    }
}
