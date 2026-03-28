<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent;

use App\Agent\Prompt\ContextWindowManager;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use PHPUnit\Framework\TestCase;

final class ContextWindowManagerTest extends TestCase
{
    public function testEstimateTokens(): void
    {
        $manager = new ContextWindowManager();
        $messages = new MessageBag(
            Message::forSystem('System prompt'),
            Message::ofUser('Hello'),
        );

        $tokens = $manager->estimateTokens($messages);
        self::assertGreaterThan(0, $tokens);
    }

    public function testUsageRatio(): void
    {
        $manager = new ContextWindowManager(maxTokens: 1000);
        $messages = new MessageBag(
            Message::forSystem(str_repeat('x', 2000)),
        );

        $ratio = $manager->getUsageRatio($messages);
        self::assertGreaterThan(0.0, $ratio);
        self::assertLessThanOrEqual(1.0, $ratio);
    }

    public function testTruncateDropsOldMessages(): void
    {
        $manager = new ContextWindowManager(
            maxTokens: 100,
            reservedForResponse: 10,
            reservedForTools: 10,
            minConversationTurns: 2,
        );

        $messages = new MessageBag(
            Message::forSystem('Sys'),
            Message::ofUser('First message - this is old'),
            Message::ofAssistant('First reply - also old'),
            Message::ofUser('Second message'),
            Message::ofAssistant('Second reply'),
            Message::ofUser('Third message - most recent'),
            Message::ofAssistant('Third reply - keep this'),
        );

        $truncated = $manager->truncate($messages);
        $all = $truncated->getMessages();

        // System message always kept
        self::assertInstanceOf(\Symfony\AI\Platform\Message\SystemMessage::class, $all[0]);

        // Should have fewer conversation messages than original
        self::assertLessThanOrEqual(\count($messages->getMessages()), \count($all));

        // Most recent messages should be kept
        $lastMsg = end($all);
        self::assertInstanceOf(\Symfony\AI\Platform\Message\AssistantMessage::class, $lastMsg);
    }

    public function testDoesNotTruncateBelowMinTurns(): void
    {
        $manager = new ContextWindowManager(
            maxTokens: 50,
            reservedForResponse: 10,
            reservedForTools: 10,
            minConversationTurns: 4,
        );

        $messages = new MessageBag(
            Message::forSystem('Sys'),
            Message::ofUser('A'),
            Message::ofAssistant('B'),
        );

        $truncated = $manager->truncate($messages);

        // Only 2 conversation messages, below min of 4 — no truncation
        self::assertCount(\count($messages->getMessages()), $truncated->getMessages());
    }
}
