<?php

declare(strict_types=1);

namespace App\Tool\Client;

use App\Server\ClientConnectionRegistry;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Execute a shell command on the connected client's local machine.
 * Only works in headless mode when a client is connected.
 */
#[AsTool('client_exec', 'Execute a shell command on the connected client\'s local machine. Only works when a client is connected to the headless server.')]
final readonly class ClientExecTool
{
    public function __construct(
        private ClientConnectionRegistry $registry,
    ) {
    }

    /**
     * @param string $command The shell command to execute on the client
     *
     * @return array{output: string, exit_code: int, error: string}|array{error: string}
     */
    public function __invoke(string $command): array
    {
        $client = $this->registry->getActiveClient();

        if ($client === null) {
            return ['error' => 'No client connected. A client must be connected via `bin/devbot client` for remote execution.'];
        }

        return $client->callTool('shell', 'exec', ['command' => $command]);
    }
}
