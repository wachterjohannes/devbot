<?php

declare(strict_types=1);

namespace App\Server;

/**
 * Represents a connected client and provides bidirectional communication.
 * The server can send tool requests to the client and wait for responses.
 */
final class ClientConnection
{
    /** @var resource */
    private mixed $socket;

    private bool $connected = true;

    public function __construct(mixed $socket)
    {
        $this->socket = $socket;
    }

    /**
     * Send a tool execution request to the client and wait for the response.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function callTool(string $tool, string $operation, array $args = []): array
    {
        if (!$this->connected) {
            return ['error' => 'Client not connected'];
        }

        $request = [
            'type' => 'tool_request',
            'tool' => $tool,
            'operation' => $operation,
            'args' => $args,
            'id' => bin2hex(random_bytes(8)),
        ];

        $json = json_encode($request, \JSON_UNESCAPED_UNICODE) . "\n";
        $written = @fwrite($this->socket, $json);

        if ($written === false || $written === 0) {
            $this->connected = false;

            return ['error' => 'Failed to send request to client'];
        }

        // Wait for response (up to 60 seconds)
        stream_set_timeout($this->socket, 60);
        $line = @fgets($this->socket);

        if ($line === false) {
            return ['error' => 'No response from client'];
        }

        $response = json_decode(trim($line), true);

        return \is_array($response) ? $response : ['error' => 'Invalid response from client'];
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }
}
