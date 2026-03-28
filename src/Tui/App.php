<?php

declare(strict_types=1);

namespace App\Tui;

use App\Heartbeat\HeartbeatLoop;
use App\Identity\Updater\ProfileLearner;
use App\Kanban\KanbanManager;
use App\Memory\Lifecycle\SessionEndHandler;
use App\Memory\MemoryManager;
use App\Tui\Widget\ChatWidget;
use App\Tui\Widget\KanbanWidget;
use App\Tui\Widget\MemoryBrowserWidget;
use App\Tui\Widget\StatusBarWidget;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\Tui\Input\KeyParser;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Root TUI application with tabbed layout.
 *
 * Tabs: F1 = Chat, F2 = Board, F3 = Memory
 * Heartbeat runs alongside the TUI for scheduled tasks.
 */
final class App
{
    private const TAB_CHAT = 0;
    private const TAB_BOARD = 1;
    private const TAB_MEMORY = 2;

    private const TAB_CONFIG = [
        self::TAB_CHAT => ['key' => 'F1', 'label' => 'Chat', 'fkey' => 'f1', 'color' => '36'],
        self::TAB_BOARD => ['key' => 'F2', 'label' => 'Board', 'fkey' => 'f2', 'color' => '33'],
        self::TAB_MEMORY => ['key' => 'F3', 'label' => 'Memory', 'fkey' => 'f3', 'color' => '32'],
    ];

    private int $activeTab = self::TAB_CHAT;
    private TextWidget $tabBar;

    public function __construct(
        private readonly AgentInterface $agent,
        private readonly HeartbeatLoop $heartbeatLoop,
        private readonly KanbanManager $kanbanManager,
        private readonly MemoryManager $memoryManager,
        private readonly SessionEndHandler $sessionEndHandler,
        private readonly ProfileLearner $profileLearner,
    ) {
    }

    public function run(): void
    {
        $tui = new Tui();

        // Chat view
        $chat = new ChatWidget($this->agent);
        $chat->setTui($tui);

        $chatView = new ContainerWidget();
        $chatView->setId('chat-view');
        $chatView->expandVertically(true);

        $chatOutput = new ContainerWidget();
        $chatOutput->setId('chat-output-container');
        $chatOutput->expandVertically(true);
        $chatOutput->add($chat->getOutput());

        $chatView->add($chatOutput);
        $chatView->add($chat->getEditor());

        // Board view
        $kanban = new KanbanWidget($this->kanbanManager);
        $kanban->setId('kanban');
        $kanban->expandVertically(true);

        $boardView = new ContainerWidget();
        $boardView->setId('board-view');
        $boardView->expandVertically(true);
        $boardView->setStyle(new Style(hidden: true));
        $boardView->add($kanban);

        // Memory view
        $memoryBrowser = new MemoryBrowserWidget($this->memoryManager);
        $memoryBrowser->setId('memory-browser');
        $memoryBrowser->expandVertically(true);

        $memoryView = new ContainerWidget();
        $memoryView->setId('memory-view');
        $memoryView->expandVertically(true);
        $memoryView->setStyle(new Style(hidden: true));
        $memoryView->add($memoryBrowser);

        // Tab bar — single TextWidget with inline ANSI colors
        $this->tabBar = new TextWidget($this->renderTabBar());
        $this->tabBar->setId('tab-bar');
        $this->tabBar->addStyleClass('bg-gray-800');
        $this->tabBar->addStyleClass('p-1');

        // Status bar
        $statusBar = new StatusBarWidget();
        $statusBar->setId('status-bar');

        // Layout
        $layout = new ContainerWidget();
        $layout->setId('main-layout');
        $layout->expandVertically(true);
        $layout->add($this->tabBar);
        $layout->add($chatView);
        $layout->add($boardView);
        $layout->add($memoryView);
        $layout->add($statusBar);

        /** @var array<int, ContainerWidget> $views */
        $views = [
            self::TAB_CHAT => $chatView,
            self::TAB_BOARD => $boardView,
            self::TAB_MEMORY => $memoryView,
        ];

        // Tab switching
        $parser = new KeyParser();
        $tui->onInput(function (string $data) use ($tui, $parser, $views, $chat, $kanban, $memoryBrowser): bool {
            foreach (self::TAB_CONFIG as $tab => $config) {
                if ($parser->matches($data, $config['fkey']) && $this->activeTab !== $tab) {
                    $this->activeTab = $tab;

                    foreach ($views as $index => $view) {
                        $view->setStyle($index === $tab ? null : new Style(hidden: true));
                    }

                    $this->tabBar->setText($this->renderTabBar());

                    match ($tab) {
                        self::TAB_CHAT => $chat->focusEditor(),
                        self::TAB_BOARD => $kanban->refresh(),
                        self::TAB_MEMORY => $memoryBrowser->refresh(),
                    };

                    $tui->requestRender(true);

                    return true;
                }
            }

            return false;
        });

        $tui->add($layout);
        $tui->quitOn('ctrl+q');

        $this->heartbeatLoop->start();
        $tui->run();
        $this->heartbeatLoop->stop();

        // Session cleanup: extract learnings and profile insights
        $this->sessionEndHandler->handle();
        $this->profileLearner->extractInsights();
    }

    private function renderTabBar(): string
    {
        // Logo
        $bar = "\033[1;97;46m DevBot \033[0m ";

        // Tabs
        foreach (self::TAB_CONFIG as $index => $config) {
            if ($index === $this->activeTab) {
                // Active: bold, colored text, lighter background
                $bar .= "\033[1;{$config['color']};48;5;239m {$config['key']} {$config['label']} \033[0m ";
            } else {
                // Inactive: dim gray
                $bar .= "\033[90m {$config['key']} {$config['label']} \033[0m ";
            }
        }

        return $bar;
    }
}
