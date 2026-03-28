<?php

declare(strict_types=1);

namespace App\Tui\Widget;

use App\Memory\MemoryManager;
use App\Memory\Model\MemoryEntry;
use App\Memory\Model\MemoryType;
use Symfony\Component\Tui\Event\SelectionChangeEvent;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;

/**
 * Memory browser: narrow list on left, wide content viewer on right.
 * No borders — maximizes readable space.
 */
final class MemoryBrowserWidget extends ContainerWidget implements VerticallyExpandableInterface
{
    private SelectListWidget $entryList;
    private TextWidget $contentView;

    /** @var array<string, MemoryEntry> */
    private array $entriesById = [];

    public function __construct(
        private readonly MemoryManager $memoryManager,
    ) {
        parent::__construct();
        // Vertical layout: list on top, content below (avoids horizontal flex issues)
        $this->entryList = new SelectListWidget([], maxVisible: 12);
        $this->entryList->setId('memory-entry-list');
        $this->entryList->onSelectionChange(function (SelectionChangeEvent $event): void {
            $this->showEntry($event->getValue());
        });

        $this->contentView = new TextWidget(' Select an entry with arrow keys.');
        $this->contentView->setId('memory-content');
        $this->contentView->addStyleClass('p-1');

        $this->add($this->entryList);
        $this->add($this->contentView);

        $this->loadEntries();
    }

    public function refresh(): void
    {
        $this->loadEntries();
        $this->focusList();
    }

    public function focusList(): void
    {
        $context = $this->entryList->getContext();
        if ($context !== null) {
            $context->getFocusManager()->setFocus($this->entryList);
        }
    }

    private function loadEntries(): void
    {
        $this->entriesById = [];
        $items = [];

        foreach ($this->memoryManager->list(MemoryType::LONG_TERM, limit: 30) as $entry) {
            $this->entriesById[$entry->id] = $entry;
            $items[] = [
                'value' => $entry->id,
                'label' => $entry->getSnippet(30),
            ];
        }

        foreach ($this->memoryManager->list(MemoryType::EPISODIC, limit: 20) as $entry) {
            $this->entriesById[$entry->id] = $entry;
            $items[] = [
                'value' => $entry->id,
                'label' => $entry->getSnippet(30),
            ];
        }

        if ($items === []) {
            $items[] = ['value' => '', 'label' => '(no entries)'];
        }

        $this->entryList->setItems($items);
    }

    private function showEntry(string $id): void
    {
        $entry = $this->entriesById[$id] ?? null;

        if ($entry === null) {
            $this->contentView->setText(' No entry selected.');

            return;
        }

        $m = $entry->metadata;
        $c = "\033[36m";
        $g = "\033[90m";
        $gr = "\033[32m";
        $r = "\033[0m";
        $b = "\033[1m";

        $tags = $m->tags !== [] ? implode(', ', $m->tags) : '-';
        $topic = $m->topic ?? '-';

        $text = " {$b}{$c}" . $entry->getSnippet(80) . "{$r}\n"
            . " {$g}{$entry->type->value}  |  {$topic}  |  {$gr}{$tags}{$r}  |  {$m->createdAt->format('Y-m-d H:i')}{$r}\n"
            . "\n"
            . ' ' . str_replace("\n", "\n ", $entry->content);

        $this->contentView->setText($text);
    }
}
