<?php

declare(strict_types=1);

namespace App\Tool\Kanban;

use App\Kanban\KanbanManager;
use App\Kanban\Model\CardStatus;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Move a card to a different column. Respects WIP limits.
 */
#[AsTool('kanban_move_card', 'Move a kanban card to a different column. Respects WIP limits.')]
final readonly class KanbanMoveCardTool
{
    public function __construct(
        private KanbanManager $kanban,
    ) {
    }

    /**
     * @param string $id     The card ID
     * @param string $status Target column: "backlog", "todo", "in_progress", "review", "done"
     *
     * @return array{moved: bool, reason?: string}
     */
    public function __invoke(string $id, string $status): array
    {
        $targetStatus = CardStatus::tryFrom($status);

        if ($targetStatus === null) {
            return ['moved' => false, 'reason' => "Invalid status '{$status}'. Use: backlog, todo, in_progress, review, done."];
        }

        return $this->kanban->moveCard($id, $targetStatus);
    }
}
