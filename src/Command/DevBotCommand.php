<?php

declare(strict_types=1);

namespace App\Command;

use App\Heartbeat\HeartbeatLoop;
use App\Server\ClientConnectionRegistry;
use App\Server\RequestHandler;
use App\Server\SocketServer;
use App\Tui\App;
use Revolt\EventLoop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Main entry point for DevBot.
 * - Default: launches the TUI chat interface
 * - --headless: runs heartbeat + socket server (no TUI, for V-Server deployment)
 */
#[AsCommand(
    name: 'run',
    description: 'Start DevBot (TUI or headless mode)',
)]
final class DevBotCommand extends Command
{
    public function __construct(
        private readonly App $app,
        private readonly HeartbeatLoop $heartbeatLoop,
        private readonly RequestHandler $requestHandler,
        private readonly ClientConnectionRegistry $clientRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('headless', null, InputOption::VALUE_NONE, 'Run without TUI (heartbeat + socket server)')
            ->addOption('socket', null, InputOption::VALUE_REQUIRED, 'Unix socket path for headless mode', '/tmp/devbot.sock');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('headless')) {
            return $this->runHeadless($input, $output);
        }

        $this->app->run();

        return Command::SUCCESS;
    }

    private function runHeadless(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $socketPath */
        $socketPath = $input->getOption('socket');

        $output->writeln('<info>DevBot headless mode</info>');
        $output->writeln("  Socket: {$socketPath}");
        $output->writeln('  Heartbeat: active');
        $output->writeln('  Press Ctrl+C to stop');
        $output->writeln('');

        $server = new SocketServer($socketPath, $this->requestHandler, $this->clientRegistry);
        $server->start();
        $this->heartbeatLoop->start();

        // Handle SIGINT/SIGTERM gracefully
        $shutdown = function () use ($server, $output): void {
            $output->writeln("\n<info>Shutting down...</info>");
            $server->stop();
            $this->heartbeatLoop->stop();
            EventLoop::getDriver()->stop();
        };

        if (\function_exists('pcntl_signal')) {
            pcntl_signal(\SIGINT, $shutdown);
            pcntl_signal(\SIGTERM, $shutdown);
        }

        $output->writeln('<info>DevBot is running. Waiting for connections...</info>');

        // Run the Revolt event loop (blocks until stopped)
        EventLoop::run();

        return Command::SUCCESS;
    }
}
