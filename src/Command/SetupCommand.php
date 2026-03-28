<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Interactive setup wizard for DevBot.
 *
 * Creates directories, configures .env.local, sets up the vector store,
 * and optionally installs a systemd service for headless mode.
 */
#[AsCommand(
    name: 'setup',
    description: 'Set up DevBot: configure environment, create directories, initialize stores',
)]
final class SetupCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('headless', null, InputOption::VALUE_NONE, 'Also configure for headless server deployment')
            ->addOption('non-interactive', null, InputOption::VALUE_NONE, 'Skip interactive prompts, use defaults/env vars');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $nonInteractive = $input->getOption('non-interactive');

        $io->title('DevBot Setup');

        // 1. Create required directories
        $this->createDirectories($io);

        // 2. Configure .env.local
        $this->configureEnvironment($io, $nonInteractive);

        // 3. Setup vector store
        $this->setupVectorStore($io, $output);

        // 4. Verify Ollama connectivity
        $this->verifyOllama($io);

        // 5. Headless server setup
        if ($input->getOption('headless')) {
            $this->setupHeadless($io, $nonInteractive);
        }

        $io->success('DevBot is ready!');

        if ($input->getOption('headless')) {
            $io->text([
                'Start the headless server:',
                '  php bin/devbot run --headless',
                '',
                'Or enable the systemd service:',
                '  sudo systemctl enable --now devbot',
            ]);
        } else {
            $io->text('Start DevBot with: php bin/devbot run');
        }

        return Command::SUCCESS;
    }

    private function createDirectories(SymfonyStyle $io): void
    {
        $io->section('Creating directories');

        $dirs = [
            'memory/long-term',
            'memory/episodic',
            'memory/short-term',
            'memory/semantic',
            'kanban',
            'heartbeat',
            'skills',
            'identity/humans',
            'var/log',
        ];

        foreach ($dirs as $dir) {
            $path = $this->projectDir . '/' . $dir;

            if (!is_dir($path)) {
                mkdir($path, 0o755, true);
                $io->text("  Created {$dir}/");
            }
        }

        $io->text('  All directories ready.');
    }

    private function configureEnvironment(SymfonyStyle $io, bool $nonInteractive): void
    {
        $io->section('Configuration');

        $envFile = $this->projectDir . '/.env.local';
        $existing = [];

        if (is_file($envFile)) {
            $existing = $this->parseEnvFile($envFile);
            $io->text('  Found existing .env.local');
        }

        if ($nonInteractive) {
            // In non-interactive mode, only create .env.local if it doesn't exist
            if (!is_file($envFile)) {
                $this->writeEnvFile($envFile, [
                    'APP_ENV' => $_ENV['APP_ENV'] ?? 'prod',
                    'APP_DEBUG' => '0',
                    'APP_SECRET' => bin2hex(random_bytes(16)),
                    'OLLAMA_HOST_URL' => $_ENV['OLLAMA_HOST_URL'] ?? 'http://localhost:11434',
                    'OLLAMA_API_KEY' => $_ENV['OLLAMA_API_KEY'] ?? '',
                    'DEVBOT_WORKDIR' => $_ENV['DEVBOT_WORKDIR'] ?? getcwd() ?: '/tmp',
                ]);
                $io->text('  Created .env.local with defaults.');
            }

            return;
        }

        // Interactive configuration
        $values = [];

        $values['APP_ENV'] = 'prod';
        $values['APP_DEBUG'] = '0';
        $values['APP_SECRET'] = $existing['APP_SECRET'] ?? bin2hex(random_bytes(16));

        $values['OLLAMA_HOST_URL'] = $io->ask(
            'Ollama API endpoint',
            $existing['OLLAMA_HOST_URL'] ?? 'http://localhost:11434',
        );

        $values['OLLAMA_API_KEY'] = $io->ask(
            'Ollama API key (for web search, get one at ollama.com/settings/keys)',
            $existing['OLLAMA_API_KEY'] ?? '',
        );

        $defaultWorkdir = $existing['DEVBOT_WORKDIR'] ?? getcwd() ?: '/tmp';
        $values['DEVBOT_WORKDIR'] = $io->ask(
            'Working directory for shell/git operations',
            $defaultWorkdir,
        );

        $this->writeEnvFile($envFile, $values);
        $io->text('  Saved .env.local');
    }

    private function setupVectorStore(SymfonyStyle $io, OutputInterface $output): void
    {
        $io->section('Vector store');

        $dbFile = $this->projectDir . '/var/devbot_memory.sqlite';

        if (is_file($dbFile)) {
            $io->text('  SQLite vector store already exists.');

            return;
        }

        $io->text('  Setting up SQLite vector store...');

        $application = $this->getApplication();

        if ($application === null) {
            $io->warning('Cannot find application, run manually: php bin/devbot ai:store:setup ai.store.sqlite.memory_store');

            return;
        }

        $storeCommand = $application->find('ai:store:setup');
        $storeInput = new ArrayInput(['store' => 'ai.store.sqlite.memory_store']);
        $storeCommand->run($storeInput, $output);

        $io->text('  Vector store ready.');
    }

    private function verifyOllama(SymfonyStyle $io): void
    {
        $io->section('Verifying Ollama');

        $host = $_ENV['OLLAMA_HOST_URL'] ?? 'http://localhost:11434';

        // Check connectivity
        $context = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $response = @file_get_contents($host . '/api/tags', false, $context);

        if ($response === false) {
            $io->warning("Cannot reach Ollama at {$host}. Make sure Ollama is running.");

            return;
        }

        $data = json_decode($response, true);
        $models = array_column($data['models'] ?? [], 'name');

        // Check required models
        $required = ['kimi-k2.5:cloud', 'nomic-embed-text'];

        foreach ($required as $model) {
            $found = false;

            foreach ($models as $installed) {
                if (str_starts_with($installed, explode(':', $model)[0])) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                $io->text("  {$model} — installed");
            } else {
                $io->warning("  {$model} — not found. Run: ollama pull {$model}");
            }
        }
    }

    private function setupHeadless(SymfonyStyle $io, bool $nonInteractive): void
    {
        $io->section('Headless server setup');

        $binary = $this->detectBinary();
        $user = get_current_user();
        $workDir = $this->projectDir;
        $socketPath = '/var/run/devbot/devbot.sock';

        if (!$nonInteractive) {
            $socketPath = $io->ask('Socket path', $socketPath);
        }

        // Generate systemd service
        $serviceContent = $this->generateSystemdService($binary, $workDir, $socketPath, $user);

        $servicePath = '/etc/systemd/system/devbot.service';
        $io->text('  Generated systemd service:');
        $io->newLine();
        $io->text($serviceContent);
        $io->newLine();

        if (!$nonInteractive && $io->confirm('Install systemd service? (requires sudo)', false)) {
            $tmpFile = sys_get_temp_dir() . '/devbot.service';
            file_put_contents($tmpFile, $serviceContent);

            $commands = [
                "sudo cp {$tmpFile} {$servicePath}",
                'sudo mkdir -p ' . \dirname($socketPath),
                'sudo chown ' . $user . ':' . $user . ' ' . \dirname($socketPath),
                'sudo systemctl daemon-reload',
            ];

            foreach ($commands as $cmd) {
                $io->text("  Running: {$cmd}");
                passthru($cmd, $exitCode);

                if ($exitCode !== 0) {
                    $io->warning("Command failed. You can install manually.");

                    return;
                }
            }

            unlink($tmpFile);
            $io->text('  Service installed. Enable with: sudo systemctl enable --now devbot');
        } else {
            $io->text("  Save the service file to {$servicePath} and run:");
            $io->text('    sudo systemctl daemon-reload');
            $io->text('    sudo systemctl enable --now devbot');
        }
    }

    private function detectBinary(): string
    {
        // Check if running as standalone FrankenPHP binary
        $binary = $_SERVER['argv'][0] ?? 'php';

        if (str_contains($binary, 'devbot') && !str_contains($binary, 'bin/devbot')) {
            // Standalone binary: ./devbot php-cli bin/devbot
            return realpath($binary) . ' php-cli bin/devbot';
        }

        // Source install: php bin/devbot
        return 'php bin/devbot';
    }

    private function generateSystemdService(string $binary, string $workDir, string $socketPath, string $user): string
    {
        // Read .env.local for env vars to forward
        $envFile = $this->projectDir . '/.env.local';
        $envLines = '';

        if (is_file($envFile)) {
            $vars = $this->parseEnvFile($envFile);

            foreach ($vars as $key => $value) {
                if ($value !== '' && !\in_array($key, ['APP_DEBUG'], true)) {
                    $envLines .= "Environment={$key}={$value}\n";
                }
            }
        }

        return <<<UNIT
[Unit]
Description=DevBot AI Agent
After=network.target ollama.service
Wants=ollama.service

[Service]
Type=simple
User={$user}
WorkingDirectory={$workDir}
ExecStart={$binary} run --headless --socket {$socketPath}
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=devbot

# Environment
{$envLines}
# Security hardening
NoNewPrivileges=true
ProtectSystem=strict
ReadWritePaths={$workDir}/var {$workDir}/memory {$workDir}/kanban {$workDir}/heartbeat {$workDir}/skills
ReadWritePaths=/var/run/devbot

[Install]
WantedBy=multi-user.target
UNIT;
    }

    /**
     * @return array<string, string>
     */
    private function parseEnvFile(string $path): array
    {
        $values = [];

        foreach (file($path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            $pos = strpos($line, '=');

            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * @param array<string, string> $values
     */
    private function writeEnvFile(string $path, array $values): void
    {
        $content = "# Generated by devbot setup\n\n";

        $sections = [
            'App' => ['APP_ENV', 'APP_DEBUG', 'APP_SECRET'],
            'Ollama' => ['OLLAMA_HOST_URL', 'OLLAMA_API_KEY'],
            'DevBot' => ['DEVBOT_WORKDIR'],
            'Telegram (optional)' => ['TELEGRAM_BOT_TOKEN', 'TELEGRAM_CHAT_ID'],
        ];

        foreach ($sections as $section => $keys) {
            $hasAny = false;

            foreach ($keys as $key) {
                if (isset($values[$key])) {
                    if (!$hasAny) {
                        $content .= "# {$section}\n";
                        $hasAny = true;
                    }

                    $content .= "{$key}={$values[$key]}\n";
                }
            }

            if ($hasAny) {
                $content .= "\n";
            }
        }

        file_put_contents($path, $content);
    }
}
