<?php

declare(strict_types=1);

namespace App\Tool\Client;

use App\Server\ClientConnectionRegistry;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Read a file from the connected client's local filesystem.
 * Only works in headless mode when a client is connected.
 */
#[AsTool('client_file_read', 'Read a file from the connected client\'s local machine. Only works when a client is connected.')]
final readonly class ClientFileReadTool
{
    public function __construct(
        private ClientConnectionRegistry $registry,
    ) {
    }

    /**
     * @param string $path Absolute path to the file on the client machine
     *
     * @return array{content: string, path: string}|array{error: string}
     */
    public function __invoke(string $path): array
    {
        $client = $this->registry->getActiveClient();

        if ($client === null) {
            return ['error' => 'No client connected.'];
        }

        return $client->callTool('filesystem', 'read', ['path' => $path]);
    }
}
