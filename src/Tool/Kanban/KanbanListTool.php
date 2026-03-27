<?php

declare(strict_types=1);

namespace App\Tool\Kanban;

use App\Kanban\KanbanManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * List the kanban board state — all columns with their cards.
 */
#[AsTool('kanban_list', 'List the kanban board. Shows all columns with cards, WIP limits, and priorities.')]
final readonly class KanbanListTool
{
    public function __construct(
        private KanbanManager $kanban,
    ) {
    }

    /**
     * @return array<string, array{name: string, wip_limit: int|null, cards: list<array<string, mixed>>}>
     */
    public function __invoke(): array
    {
        return $this->kanban->getBoardSummary();
    }
}
