<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Client that connects to a headless DevBot server.
 *
 * Local:  bin/devbot client
 * Remote: bin/devbot client --host user@server
 *
 * When connecting remotely, an SSH tunnel is established to forward
 * the Unix socket. The client can also expose local tools back to
 * the server via a reverse listener.
 */
#[AsCommand(
    name: 'client',
    description: 'Connect to a headless DevBot server',
)]
final class ClientCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'SSH host (e.g. user@server) for remote connection')
            ->addOption('socket', null, InputOption::VALUE_REQUIRED, 'Unix socket path', '/tmp/devbot.sock')
            ->addArgument('message', InputArgument::OPTIONAL, 'Single message (non-interactive mode)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $socketPath */
        $socketPath = $input->getOption('socket');
        /** @var string|null $host */
        $host = $input->getOption('host');
        /** @var string|null $singleMessage */
        $singleMessage = $input->getArgument('message');

        $sshProcess = null;
        $localSocket = $socketPath;

        if ($host !== null) {
            // Remote: create SSH tunnel for the Unix socket
            $localSocket = '/tmp/devbot-client-' . getmypid() . '.sock';
            $sshProcess = $this->createSshTunnel($host, $socketPath, $localSocket, $io);

            if ($sshProcess === null) {
                return Command::FAILURE;
            }

            // Wait for tunnel to establish
            usleep(500000);
        }

        // Connect to the socket
        $socket = @stream_socket_client('unix://' . $localSocket, $errno, $errstr, 5);

        if ($socket === false) {
            $io->error("Cannot connect to DevBot at {$localSocket}: {$errstr}");
            $sshProcess?->stop();

            return Command::FAILURE;
        }

        // Ping to verify connection
        $pong = $this->sendRequest($socket, ['type' => 'ping']);
        if (($pong['type'] ?? '') !== 'pong') {
            $io->error('DevBot server not responding.');
            fclose($socket);
            $sshProcess?->stop();

            return Command::FAILURE;
        }

        if ($singleMessage !== null) {
            // Non-interactive: send one message, print response, exit
            $response = $this->sendRequest($socket, ['type' => 'chat', 'message' => $singleMessage]);
            $output->writeln($response['content'] ?? $response['message'] ?? 'No response');
            fclose($socket);
            $sshProcess?->stop();

            return Command::SUCCESS;
        }

        // Interactive mode
        $io->title('DevBot Client' . ($host !== null ? " (connected to {$host})" : ''));
        $io->text('Type a message and press Enter. Type "quit" to exit.');
        $io->newLine();

        while (true) {
            $message = $io->ask('You');

            if ($message === null || $message === 'quit' || $message === 'exit') {
                break;
            }

            if ($message === '/reset') {
                $this->sendRequest($socket, ['type' => 'reset']);
                $io->success('Conversation reset.');

                continue;
            }

            $response = $this->sendRequest($socket, ['type' => 'chat', 'message' => $message]);

            if (($response['type'] ?? '') === 'error') {
                $io->error($response['message'] ?? 'Unknown error');
            } else {
                $io->newLine();
                $output->writeln('<fg=cyan>DevBot:</> ' . ($response['content'] ?? ''));
                $io->newLine();
            }
        }

        fclose($socket);

        if ($sshProcess !== null) {
            $sshProcess->stop();
            @unlink($localSocket);
        }

        $io->success('Disconnected.');

        return Command::SUCCESS;
    }

    private function createSshTunnel(string $host, string $remoteSocket, string $localSocket, SymfonyStyle $io): ?Process
    {
        // Clean up stale local socket
        if (file_exists($localSocket)) {
            unlink($localSocket);
        }

        $io->text("Establishing SSH tunnel to {$host}...");

        // SSH local socket forwarding: -L local_socket:remote_socket
        $process = new Process([
            'ssh', '-N', '-L', "{$localSocket}:{$remoteSocket}", $host,
        ]);

        $process->setTimeout(null);
        $process->start();

        if (!$process->isRunning()) {
            $io->error('Failed to establish SSH tunnel: ' . $process->getErrorOutput());

            return null;
        }

        $io->text('SSH tunnel established.');

        return $process;
    }

    /**
     * @param resource             $socket
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function sendRequest(mixed $socket, array $request): array
    {
        $json = json_encode($request, \JSON_UNESCAPED_UNICODE) . "\n";
        fwrite($socket, $json);

        // Read response (wait up to 5 minutes for agent calls)
        stream_set_timeout($socket, 300);
        $line = fgets($socket);

        if ($line === false) {
            return ['type' => 'error', 'message' => 'No response from server'];
        }

        $response = json_decode(trim($line), true);

        return \is_array($response) ? $response : ['type' => 'error', 'message' => 'Invalid response'];
    }
}
