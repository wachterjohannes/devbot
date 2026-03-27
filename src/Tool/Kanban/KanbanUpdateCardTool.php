<?php

declare(strict_types=1);

namespace App\Tool\Kanban;

use App\Kanban\KanbanManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Update card details (title, description, labels, assignee, priority).
 */
#[AsTool('kanban_update_card', 'Update a kanban card. Can change title, description, labels, assignee, or priority.')]
final readonly class KanbanUpdateCardTool
{
    public function __construct(
        private KanbanManager $kanban,
    ) {
    }

    /**
     * @param string            $id          The card ID
     * @param string|null       $title       New title (null to keep)
     * @param string|null       $description New description (null to keep)
     * @param list<string>|null $labels      New labels (null to keep)
     * @param string|null       $assignee    New assignee (null to keep)
     * @param string|null       $priority    New priority (null to keep)
     *
     * @return array{updated: bool, card?: array<string, mixed>}
     */
    public function __invoke(
        string $id,
        ?string $title = null,
        ?string $description = null,
        ?array $labels = null,
        ?string $assignee = null,
        ?string $priority = null,
    ): array {
        $card = $this->kanban->updateCard($id, $title, $description, $labels, $assignee, $priority);

        if ($card === null) {
            return ['updated' => false];
        }

        return [
            'updated' => true,
            'card' => $card->jsonSerialize(),
        ];
    }
}
