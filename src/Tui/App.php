<?php

declare(strict_types=1);

namespace App\Tui;

use App\Tui\Widget\ChatWidget;
use App\Tui\Widget\StatusBarWidget;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;

/**
 * Root TUI application. Lays out: chat output (expands) + editor (fixed) + status bar.
 *
 * Phase 1: Single-tab chat interface.
 * Phase 4 adds tabs (Board, Memory, Logs).
 */
final class App
{
    public function __construct(
        private readonly AgentInterface $agent,
    ) {
    }

    public function run(): void
    {
        $tui = new Tui();

        $chat = new ChatWidget($this->agent);

        $layout = new ContainerWidget();
        $layout->setId('main-layout');
        $layout->expandVertically(true);

        // Output area — wrapped in expanding container to fill available space
        $outputContainer = new ContainerWidget();
        $outputContainer->setId('output-container');
        $outputContainer->expandVertically(true);
        $outputContainer->add($chat->getOutput());

        $layout->add($outputContainer);
        $layout->add($chat->getEditor());
        $layout->add(new StatusBarWidget());

        $chat->setTui($tui);

        $tui->add($layout);
        $tui->quitOn('ctrl+q');
        $tui->run();
    }
}
