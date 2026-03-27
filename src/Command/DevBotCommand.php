<?php

declare(strict_types=1);

namespace App\Command;

use App\Tui\App;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Main entry point for DevBot. Launches the TUI chat interface.
 */
#[AsCommand(
    name: 'run',
    description: 'Start the DevBot TUI agent',
)]
final class DevBotCommand extends Command
{
    public function __construct(
        private readonly App $app,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('headless', null, InputOption::VALUE_NONE, 'Run without TUI (heartbeat + Telegram only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('headless')) {
            $output->writeln('<info>DevBot running in headless mode (not yet implemented)</info>');

            return Command::SUCCESS;
        }

        $this->app->run();

        return Command::SUCCESS;
    }
}
