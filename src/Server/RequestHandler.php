<?php

declare(strict_types=1);

namespace App\Server;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Handles JSON requests from the socket server.
 * Routes to the agent for chat, or handles control commands.
 */
final class RequestHandler
{
    private MessageBag $messages;

    public function __construct(
        private readonly AgentInterface $agent,
    ) {
        $this->messages = new MessageBag(
            Message::forSystem('You are DevBot, an AI development agent. Be direct and helpful.'),
        );
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function handle(array $request): array
    {
        $type = $request['type'] ?? '';

        return match ($type) {
            'ping' => ['type' => 'pong'],
            'chat' => $this->handleChat($request),
            'reset' => $this->handleReset(),
            default => ['type' => 'error', 'message' => "Unknown request type: {$type}"],
        };
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function handleChat(array $request): array
    {
        $message = $request['message'] ?? '';

        if ($message === '') {
            return ['type' => 'error', 'message' => 'Empty message'];
        }

        $this->messages->add(Message::ofUser($message));

        try {
            $result = $this->agent->call($this->messages);
            $content = $result->getContent();
            $response = \is_string($content) ? $content : '(non-text response)';

            $this->messages->add(Message::ofAssistant($response));

            return [
                'type' => 'response',
                'content' => $response,
            ];
        } catch (\Throwable $e) {
            return [
                'type' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function handleReset(): array
    {
        $this->messages = new MessageBag(
            Message::forSystem('You are DevBot, an AI development agent. Be direct and helpful.'),
        );

        return ['type' => 'ok', 'message' => 'Conversation reset'];
    }
}
