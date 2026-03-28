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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Main entry point for DevBot.
 * - Default: launches the TUI chat interface (or connects to headless server if running)
 * - --headless: runs heartbeat + socket server (no TUI, for V-Server deployment)
 */
#[AsCommand(
    name: 'run',
    description: 'Start DevBot (TUI or headless mode)',
)]
final class DevBotCommand extends Command
{
    private const SOCKET_PATHS = [
        '/tmp/devbot.sock',
        '/var/run/devbot/devbot.sock',
    ];

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
            ->addOption('socket', null, InputOption::VALUE_REQUIRED, 'Unix socket path for headless mode', '/tmp/devbot.sock')
            ->addOption('standalone', null, InputOption::VALUE_NONE, 'Force standalone TUI (skip headless server detection)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('headless')) {
            return $this->runHeadless($input, $output);
        }

        // Check if a headless server is already running
        if (!$input->getOption('standalone')) {
            $activeSocket = $this->detectHeadlessServer($input);

            if ($activeSocket !== null) {
                $output->writeln('<info>Headless server detected at ' . $activeSocket . ', connecting as client...</info>');
                $output->writeln('');

                return $this->connectAsClient($activeSocket, $output);
            }
        }

        $this->app->run();

        return Command::SUCCESS;
    }

    /**
     * Detect an active headless server by checking known socket paths.
     */
    private function detectHeadlessServer(InputInterface $input): ?string
    {
        /** @var string $configuredSocket */
        $configuredSocket = $input->getOption('socket');

        $candidates = array_unique([$configuredSocket, ...self::SOCKET_PATHS]);

        foreach ($candidates as $socketPath) {
            if (!file_exists($socketPath)) {
                continue;
            }

            // Try to connect and ping — confirms it's a live server, not a stale socket
            $socket = @stream_socket_client('unix://' . $socketPath, $errno, $errstr, 2);

            if ($socket === false) {
                continue;
            }

            fwrite($socket, json_encode(['type' => 'ping']) . "\n");
            stream_set_timeout($socket, 2);
            $response = fgets($socket);
            fclose($socket);

            if ($response !== false) {
                $data = json_decode(trim($response), true);

                if (\is_array($data) && ($data['type'] ?? '') === 'pong') {
                    return $socketPath;
                }
            }
        }

        return null;
    }

    /**
     * Delegate to the client command to connect to the headless server.
     */
    private function connectAsClient(string $socketPath, OutputInterface $output): int
    {
        $application = $this->getApplication();

        if ($application === null) {
            $output->writeln('<error>Cannot find application</error>');

            return Command::FAILURE;
        }

        $clientCommand = $application->find('client');

        return $clientCommand->run(new ArrayInput(['--socket' => $socketPath]), $output);
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
