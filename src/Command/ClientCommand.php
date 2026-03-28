<?php

declare(strict_types=1);

namespace App\Command;

use App\Server\ClientToolExecutor;
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
 * The client exposes local tools (filesystem, shell) that the server
 * can call back during agent execution (reverse tool execution).
 */
#[AsCommand(
    name: 'client',
    description: 'Connect to a headless DevBot server',
)]
final class ClientCommand extends Command
{
    private ClientToolExecutor $toolExecutor;

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

        $this->toolExecutor = new ClientToolExecutor();
        $sshProcess = null;
        $localSocket = $socketPath;

        if ($host !== null) {
            $localSocket = '/tmp/devbot-client-' . getmypid() . '.sock';
            $sshProcess = $this->createSshTunnel($host, $socketPath, $localSocket, $io);

            if ($sshProcess === null) {
                return Command::FAILURE;
            }

            usleep(500000);
        }

        $socket = @stream_socket_client('unix://' . $localSocket, $errno, $errstr, 5);

        if ($socket === false) {
            $io->error("Cannot connect to DevBot at {$localSocket}: {$errstr}");
            $sshProcess?->stop();

            return Command::FAILURE;
        }

        $pong = $this->sendRequest($socket, ['type' => 'ping'], $output);
        if (($pong['type'] ?? '') !== 'pong') {
            $io->error('DevBot server not responding.');
            fclose($socket);
            $sshProcess?->stop();

            return Command::FAILURE;
        }

        if ($singleMessage !== null) {
            $response = $this->sendRequest($socket, ['type' => 'chat', 'message' => $singleMessage], $output);
            $output->writeln($response['content'] ?? $response['message'] ?? 'No response');
            fclose($socket);
            $sshProcess?->stop();

            return Command::SUCCESS;
        }

        // Interactive mode
        $io->title('DevBot Client' . ($host !== null ? " (connected to {$host})" : ''));
        $io->text('Type a message and press Enter. Type "quit" to exit.');
        $io->text('Your local tools (filesystem, shell) are exposed to the server.');
        $io->newLine();

        while (true) {
            $message = $io->ask('You');

            if ($message === null || $message === 'quit' || $message === 'exit') {
                break;
            }

            if ($message === '/reset') {
                $this->sendRequest($socket, ['type' => 'reset'], $output);
                $io->success('Conversation reset.');

                continue;
            }

            $response = $this->sendRequest($socket, ['type' => 'chat', 'message' => $message], $output);

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
        if (file_exists($localSocket)) {
            unlink($localSocket);
        }

        $io->text("Establishing SSH tunnel to {$host}...");

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
     * Send a request and read the response, handling any tool_request callbacks
     * from the server (reverse tool execution) along the way.
     *
     * @param resource             $socket
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function sendRequest(mixed $socket, array $request, OutputInterface $output): array
    {
        $json = json_encode($request, \JSON_UNESCAPED_UNICODE) . "\n";
        fwrite($socket, $json);

        return $this->readResponse($socket, $output);
    }

    /**
     * Read from socket, handling tool_request messages by executing locally
     * and sending back tool_response, until a final response arrives.
     *
     * @param resource $socket
     * @return array<string, mixed>
     */
    private function readResponse(mixed $socket, OutputInterface $output): array
    {
        stream_set_timeout($socket, 300);

        while (true) {
            $line = fgets($socket);

            if ($line === false) {
                return ['type' => 'error', 'message' => 'No response from server'];
            }

            $message = json_decode(trim($line), true);

            if (!\is_array($message)) {
                return ['type' => 'error', 'message' => 'Invalid response'];
            }

            // Handle reverse tool execution
            if (($message['type'] ?? '') === 'tool_request') {
                $tool = $message['tool'] ?? 'unknown';
                $op = $message['operation'] ?? 'unknown';
                $output->writeln("<fg=gray>  [client] executing {$tool}:{$op}...</>");

                $result = $this->toolExecutor->execute($message);
                $resultJson = json_encode($result, \JSON_UNESCAPED_UNICODE) . "\n";
                fwrite($socket, $resultJson);

                // Continue reading — the final response comes after tool execution
                continue;
            }

            // Any other message type is the final response
            return $message;
        }
    }
}
