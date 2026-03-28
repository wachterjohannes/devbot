<?php

declare(strict_types=1);

namespace App\Agent\Prompt;

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Manages the context window budget by truncating old conversation turns.
 *
 * Budget allocation priority:
 *  1. System prompt + identity (always kept)
 *  2. Tool schemas (always kept — handled by the platform)
 *  3. Recent conversation turns (truncated from oldest)
 *  4. Memory injection (kept — injected by MemoryInjectionProcessor)
 *
 * Uses a simple character-based estimation (1 token ~ 4 chars) since
 * we don't have a tokenizer for Ollama models.
 */
final readonly class ContextWindowManager
{
    private const CHARS_PER_TOKEN = 4;

    public function __construct(
        private int $maxTokens = 128000,
        private int $reservedForResponse = 4000,
        private int $reservedForTools = 8000,
        private int $minConversationTurns = 4,
    ) {
    }

    /**
     * Truncate the message bag to fit within the context window.
     * Keeps the system message and the most recent conversation turns.
     */
    public function truncate(MessageBag $messageBag): MessageBag
    {
        $messages = $messageBag->getMessages();

        $budget = ($this->maxTokens - $this->reservedForResponse - $this->reservedForTools) * self::CHARS_PER_TOKEN;

        // Separate system messages from conversation
        $systemMessages = [];
        $conversationMessages = [];

        foreach ($messages as $message) {
            if ($message instanceof SystemMessage) {
                $systemMessages[] = $message;
            } else {
                $conversationMessages[] = $message;
            }
        }

        // System messages always kept — subtract from budget
        $systemChars = 0;
        foreach ($systemMessages as $msg) {
            $systemChars += $this->estimateMessageChars($msg);
        }
        $budget -= $systemChars;

        if ($budget <= 0 || \count($conversationMessages) <= $this->minConversationTurns) {
            return $messageBag;
        }

        // Keep most recent turns, drop oldest until we fit
        $kept = [];
        $usedChars = 0;

        // Work backwards from newest
        for ($i = \count($conversationMessages) - 1; $i >= 0; --$i) {
            $msgChars = $this->estimateMessageChars($conversationMessages[$i]);

            if ($usedChars + $msgChars > $budget && \count($kept) >= $this->minConversationTurns) {
                break;
            }

            $kept[] = $conversationMessages[$i];
            $usedChars += $msgChars;
        }

        // Reverse to restore chronological order
        $kept = array_reverse($kept);

        // Rebuild message bag
        $truncated = new MessageBag(...$systemMessages, ...$kept);

        return $truncated;
    }

    /**
     * Estimate the token count of a message bag.
     */
    public function estimateTokens(MessageBag $messageBag): int
    {
        $chars = 0;

        foreach ($messageBag->getMessages() as $message) {
            $chars += $this->estimateMessageChars($message);
        }

        return (int) ceil($chars / self::CHARS_PER_TOKEN);
    }

    /**
     * Check if the message bag is approaching the context limit.
     *
     * @return float Usage ratio 0.0-1.0
     */
    public function getUsageRatio(MessageBag $messageBag): float
    {
        $tokens = $this->estimateTokens($messageBag);

        return min(1.0, $tokens / $this->maxTokens);
    }

    private function estimateMessageChars(MessageInterface $message): int
    {
        if ($message instanceof SystemMessage) {
            $content = $message->getContent();

            return mb_strlen(\is_string($content) ? $content : (string) $content);
        }

        if ($message instanceof UserMessage) {
            $chars = 0;
            foreach ($message->getContent() as $content) {
                $chars += $content instanceof Text ? mb_strlen($content->getText()) : 100;
            }

            return $chars;
        }

        if ($message instanceof AssistantMessage) {
            return mb_strlen($message->getContent() ?? '');
        }

        return 100; // fallback estimate for unknown message types
    }
}
