<?php

declare(strict_types=1);

namespace App\Tui\Widget;

use App\Kanban\KanbanManager;
use App\Kanban\Model\Card;
use App\Kanban\Model\CardStatus;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;

/**
 * Kanban board TUI widget.
 * Renders columns side-by-side with cards. Refreshes from KanbanManager on each render.
 */
final class KanbanWidget extends ContainerWidget implements VerticallyExpandableInterface
{
    public function __construct(
        private readonly KanbanManager $kanbanManager,
    ) {
        parent::__construct();
        $this->setStyle(new Style(direction: Direction::Horizontal, gap: 1));
        $this->refresh();
    }

    /**
     * Rebuild the widget tree from current board state.
     */
    public function refresh(): void
    {
        $this->clear();

        $columns = [
            [CardStatus::BACKLOG, 'Backlog', 'gray-500'],
            [CardStatus::TODO, 'To Do', 'blue-400'],
            [CardStatus::IN_PROGRESS, 'In Progress', 'yellow-400'],
            [CardStatus::REVIEW, 'Review', 'purple-400'],
            [CardStatus::DONE, 'Done', 'green-400'],
        ];

        $board = $this->kanbanManager->getBoard();

        foreach ($columns as [$status, $name, $style]) {
            $column = $this->buildColumn($status, $name, $style, $board->getCardsByStatus($status));
            $this->add($column);
        }
    }

    /**
     * @param list<Card> $cards
     */
    private function buildColumn(CardStatus $status, string $name, string $colorClass, array $cards): ContainerWidget
    {
        $column = new ContainerWidget();
        $column->setId('col-' . $status->value);
        $column->addStyleClass('flex-1');
        $column->addStyleClass('border-1');
        $column->addStyleClass('border-rounded');
        $column->addStyleClass('border-' . $colorClass);

        // Column header
        $wipInfo = $this->getWipInfo($status);
        $header = new TextWidget(" {$name} ({$this->count($status)}){$wipInfo}");
        $header->addStyleClass('bold');
        $header->addStyleClass('text-' . $colorClass);
        $header->addStyleClass('p-1');
        $column->add($header);

        // Cards
        if ($cards === []) {
            $empty = new TextWidget(' (empty)');
            $empty->addStyleClass('dim');
            $empty->addStyleClass('p-1');
            $column->add($empty);
        } else {
            foreach ($cards as $card) {
                $column->add($this->buildCard($card));
            }
        }

        return $column;
    }

    private function buildCard(Card $card): TextWidget
    {
        $priority = match ($card->priority) {
            'critical' => '!!',
            'high' => '! ',
            'low' => '  ',
            default => '  ',
        };

        $assignee = $card->assignee !== null ? " @{$card->assignee}" : '';
        $labels = $card->labels !== [] ? ' [' . implode(', ', $card->labels) . ']' : '';

        $text = " {$priority}{$card->title}{$assignee}{$labels}";

        $widget = new TextWidget($text, truncate: true);
        $widget->addStyleClass('p-1');

        if ($card->priority === 'critical' || $card->priority === 'high') {
            $widget->addStyleClass('text-red-400');
        }

        return $widget;
    }

    private function count(CardStatus $status): int
    {
        return \count($this->kanbanManager->getBoard()->getCardsByStatus($status));
    }

    private function getWipInfo(CardStatus $status): string
    {
        foreach ($this->kanbanManager->getBoard()->columns as $column) {
            if ($column->status === $status && $column->wipLimit !== null) {
                return "/{$column->wipLimit}";
            }
        }

        return '';
    }
}
