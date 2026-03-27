<?php

declare(strict_types=1);

namespace App\Tui\Widget;

use Revolt\EventLoop;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Bridge\Ollama\OllamaMessageChunk;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Chat controller: wires output and editor widgets, handles agent interaction.
 * Streams responses token-by-token via vendor patch (PR #1827 NdjsonHttpResult).
 */
final class ChatWidget
{
    private TextWidget $output;
    private EditorWidget $editor;
    private MessageBag $messages;
    private string $conversationLog = '';
    private bool $waiting = false;
    private ?Tui $tui = null;

    public function __construct(
        private readonly AgentInterface $agent,
    ) {
        $this->messages = new MessageBag(
            Message::forSystem('You are DevBot, an AI development agent. Be direct and helpful.'),
        );

        $this->output = new TextWidget("DevBot ready. Type a message and press Ctrl+Enter to send.");
        $this->output->setId('chat-output');
        $this->output->addStyleClass('p-1');

        $this->editor = new EditorWidget();
        $this->editor->setId('chat-input');
        $this->editor->setLabel('Message');
        $this->editor->setMinVisibleLines(3);
        $this->editor->setMaxVisibleLines(5);
        $this->editor->addStyleClass('border-1');
        $this->editor->addStyleClass('border-rounded');
        $this->editor->addStyleClass('border-gray-600');
        $this->editor->addStyleClass('p-1');

        $this->editor->onSubmit(function (SubmitEvent $event): void {
            if ($event->isEmpty() || $this->waiting) {
                return;
            }

            $this->handleUserMessage($event->getValue());
            $this->editor->setText('');
        });
    }

    public function setTui(Tui $tui): void
    {
        $this->tui = $tui;
    }

    public function getOutput(): TextWidget
    {
        return $this->output;
    }

    public function getEditor(): EditorWidget
    {
        return $this->editor;
    }

    private function handleUserMessage(string $text): void
    {
        $this->waiting = true;
        $this->conversationLog .= "\n\nYou: {$text}";
        $this->updateOutput($this->conversationLog . "\n\nDevBot: thinking...");

        $this->messages->add(Message::ofUser($text));

        EventLoop::defer(function () {
            try {
                $result = $this->agent->call($this->messages, ['stream' => true]);
                $content = $result->getContent();

                if ($content instanceof \Generator) {
                    $this->streamResponse($content);
                } elseif (\is_string($content)) {
                    $this->messages->add(Message::ofAssistant($content));
                    $this->conversationLog .= "\n\nDevBot: {$content}";
                    $this->updateOutput($this->conversationLog);
                }
            } catch (\Throwable $e) {
                $this->conversationLog .= "\n\nDevBot: Error: " . $e->getMessage();
                $this->updateOutput($this->conversationLog);
            } finally {
                $this->waiting = false;
            }
        });
    }

    private function streamResponse(\Generator $chunks): void
    {
        $fullResponse = '';
        $isThinking = true;
        $thinkingDots = 0;
        $this->conversationLog .= "\n\nDevBot: ";

        foreach ($chunks as $chunk) {
            if ($chunk instanceof OllamaMessageChunk) {
                $thinking = $chunk->getThinking();
                $content = $chunk->getContent();

                if ($thinking !== null && $thinking !== '') {
                    // Still in thinking phase — animate dots
                    if ($isThinking && ++$thinkingDots % 5 === 0) {
                        $dots = str_repeat('.', ($thinkingDots / 5 % 3) + 1);
                        $this->updateOutput($this->conversationLog . "thinking" . $dots);
                    }

                    continue;
                }

                if ($content !== null && $content !== '') {
                    if ($isThinking) {
                        $isThinking = false;
                    }

                    $fullResponse .= $content;
                    $this->updateOutput($this->conversationLog . $fullResponse);
                }
            } else {
                $text = (string) $chunk;
                if ($text !== '') {
                    $fullResponse .= $text;
                    $this->updateOutput($this->conversationLog . $fullResponse);
                }
            }
        }

        if ($fullResponse === '') {
            $fullResponse = '(empty response)';
        }

        $this->conversationLog .= $fullResponse;
        $this->messages->add(Message::ofAssistant($fullResponse));
    }

    /**
     * Update the output widget and request an immediate TUI re-render.
     */
    private function updateOutput(string $text): void
    {
        $this->output->setText($text);
        $this->tui?->requestRender();
    }
}
