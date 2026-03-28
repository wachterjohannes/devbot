<?php

declare(strict_types=1);

namespace App\Server;

/**
 * Tracks connected clients. Tools use this to forward operations to the client.
 * Only one active client is supported at a time (last connected wins).
 */
final class ClientConnectionRegistry
{
    private ?ClientConnection $activeClient = null;

    public function register(ClientConnection $connection): void
    {
        $this->activeClient = $connection;
    }

    public function unregister(ClientConnection $connection): void
    {
        if ($this->activeClient === $connection) {
            $this->activeClient = null;
        }
    }

    public function getActiveClient(): ?ClientConnection
    {
        if ($this->activeClient !== null && !$this->activeClient->isConnected()) {
            $this->activeClient = null;
        }

        return $this->activeClient;
    }

    public function hasClient(): bool
    {
        return $this->getActiveClient() !== null;
    }
}
