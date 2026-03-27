<?php

declare(strict_types=1);

namespace App\Tui;

use App\Tui\Widget\ChatWidget;
use App\Tui\Widget\StatusBarWidget;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;

/**
 * Root TUI application. Sets up the layout with chat panel and status bar.
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
        $chat->setId('chat');
        $chat->expandVertically(true);

        $statusBar = new StatusBarWidget();
        $statusBar->setId('status-bar');

        $layout = new ContainerWidget();
        $layout->setId('main-layout');
        $layout->expandVertically(true);
        $layout->add($chat);
        $layout->add($statusBar);

        $tui->add($layout);
        $tui->quitOn('ctrl+q');
        $tui->run();
    }
}
