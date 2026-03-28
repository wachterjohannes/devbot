<?php

declare(strict_types=1);

namespace App\Server;

use Revolt\EventLoop;

/**
 * Unix socket server for headless mode.
 * Accepts JSON commands, processes them via a handler, returns JSON responses.
 *
 * Protocol (newline-delimited JSON):
 *   Request:  {"type": "chat", "message": "..."}\n
 *   Response: {"type": "response", "content": "..."}\n
 *   Request:  {"type": "tool_call", "tool": "...", "args": {...}}\n
 *   Response: {"type": "tool_result", "result": {...}}\n
 *   Request:  {"type": "ping"}\n
 *   Response: {"type": "pong"}\n
 */
final class SocketServer
{
    private ?string $callbackId = null;

    /** @var resource|null */
    private $socket = null;

    /** @var list<resource> */
    private array $clients = [];

    public function __construct(
        private readonly string $socketPath,
        private readonly RequestHandler $handler,
    ) {
    }

    /**
     * Start listening on the Unix socket within the Revolt event loop.
     */
    public function start(): void
    {
        // Clean up stale socket
        if (file_exists($this->socketPath)) {
            unlink($this->socketPath);
        }

        $dir = \dirname($this->socketPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $socket = stream_socket_server('unix://' . $this->socketPath, $errno, $errstr);

        if ($socket === false) {
            throw new \RuntimeException("Failed to create socket: {$errstr} ({$errno})");
        }

        stream_set_blocking($socket, false);
        $this->socket = $socket;

        // Accept connections in the event loop
        $this->callbackId = EventLoop::onReadable($socket, function ($callbackId, $socket): void {
            $client = @stream_socket_accept($socket, 0);

            if ($client === false) {
                return;
            }

            stream_set_blocking($client, false);
            $this->clients[] = $client;

            $buffer = '';
            EventLoop::onReadable($client, function (string $cbId) use ($client, &$buffer): void {
                $data = @fread($client, 8192);

                if ($data === false || $data === '') {
                    EventLoop::cancel($cbId);
                    $this->removeClient($client);

                    return;
                }

                $buffer .= $data;

                while (false !== ($pos = strpos($buffer, "\n"))) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $line = trim($line);

                    if ($line === '') {
                        continue;
                    }

                    $request = json_decode($line, true);

                    if (!\is_array($request)) {
                        $this->send($client, ['type' => 'error', 'message' => 'Invalid JSON']);

                        continue;
                    }

                    $response = $this->handler->handle($request);
                    $this->send($client, $response);
                }
            });
        });
    }

    public function stop(): void
    {
        if ($this->callbackId !== null) {
            EventLoop::cancel($this->callbackId);
            $this->callbackId = null;
        }

        foreach ($this->clients as $client) {
            @fclose($client);
        }
        $this->clients = [];

        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }

        if (file_exists($this->socketPath)) {
            @unlink($this->socketPath);
        }
    }

    /**
     * @param resource             $client
     * @param array<string, mixed> $data
     */
    private function send(mixed $client, array $data): void
    {
        $json = json_encode($data, \JSON_UNESCAPED_UNICODE) . "\n";
        @fwrite($client, $json);
    }

    /**
     * @param resource $client
     */
    private function removeClient(mixed $client): void
    {
        @fclose($client);
        $this->clients = array_values(array_filter(
            $this->clients,
            static fn ($c) => $c !== $client,
        ));
    }
}
