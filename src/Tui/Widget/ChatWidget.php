<?php

declare(strict_types=1);

namespace App\Tui\Widget;

use Revolt\EventLoop;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;

/**
 * Chat panel: markdown output area + editor input.
 * Sends user messages to the agent and displays responses.
 */
final class ChatWidget extends ContainerWidget implements VerticallyExpandableInterface
{
    private MarkdownWidget $output;
    private EditorWidget $editor;
    private MessageBag $messages;
    private string $conversationLog = '';

    public function __construct(
        private readonly AgentInterface $agent,
    ) {
        parent::__construct();

        $this->messages = new MessageBag(
            Message::forSystem('You are DevBot, an AI development agent. Be direct and helpful.'),
        );

        $this->output = new MarkdownWidget('*DevBot ready. Type a message and press Ctrl+Enter to send.*');
        $this->output->setId('chat-output');

        $this->editor = new EditorWidget();
        $this->editor->setId('chat-input');
        $this->editor->setMinVisibleLines(3);
        $this->editor->setMaxVisibleLines(6);

        $this->editor->onSubmit(function (SubmitEvent $event): void {
            if ($event->isEmpty()) {
                return;
            }

            $this->handleUserMessage($event->getValue());
            $this->editor->setText('');
        });

        $this->add($this->output);
        $this->add($this->editor);
    }

    private function handleUserMessage(string $text): void
    {
        $this->conversationLog .= "\n\n---\n\n**You:** {$text}";
        $this->output->setText($this->conversationLog . "\n\n**DevBot:** _thinking..._");

        $this->messages->add(Message::ofUser($text));

        // Run agent call in a Fiber so TUI stays responsive
        EventLoop::queue(function () {
            try {
                $result = $this->agent->call($this->messages);
                $response = $result->getContent();

                $this->messages->add(Message::ofAssistant($response));
                $this->conversationLog .= "\n\n**DevBot:** {$response}";
                $this->output->setText($this->conversationLog);
            } catch (\Throwable $e) {
                $this->conversationLog .= "\n\n**DevBot:** _Error: " . $e->getMessage() . '_';
                $this->output->setText($this->conversationLog);
            }
        });
    }
}
