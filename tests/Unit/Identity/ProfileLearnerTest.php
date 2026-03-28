<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Updater\ProfileLearner;
use App\Memory\Store\ShortTermStore;
use PHPUnit\Framework\TestCase;

final class ProfileLearnerTest extends TestCase
{
    public function testExtractsPreferences(): void
    {
        $store = new ShortTermStore();
        $store->addTurn('user', 'I prefer small commits over large ones');
        $store->addTurn('assistant', 'Noted, I will keep commits small.');

        $learner = new ProfileLearner($store);
        $insights = $learner->extractInsights();

        self::assertCount(1, $insights);
        self::assertSame('Preferences', $insights[0]['section']);
        self::assertStringContainsString('small commits', $insights[0]['insight']);
    }

    public function testExtractsTechContext(): void
    {
        $store = new ShortTermStore();
        $store->addTurn('user', 'We use PostgreSQL and Redis in production');

        $learner = new ProfileLearner($store);
        $insights = $learner->extractInsights();

        self::assertCount(1, $insights);
        self::assertSame('Tech Context', $insights[0]['section']);
    }

    public function testExtractsBasicInfo(): void
    {
        $store = new ShortTermStore();
        $store->addTurn('user', 'I am a senior developer');

        $learner = new ProfileLearner($store);
        $insights = $learner->extractInsights();

        self::assertCount(1, $insights);
        self::assertSame('Basics', $insights[0]['section']);
    }

    public function testIgnoresAssistantMessages(): void
    {
        $store = new ShortTermStore();
        $store->addTurn('assistant', 'I prefer to use TypeScript');

        $learner = new ProfileLearner($store);
        $insights = $learner->extractInsights();

        self::assertCount(0, $insights);
    }

    public function testDeduplicates(): void
    {
        $store = new ShortTermStore();
        $store->addTurn('user', 'I prefer PSR-12');
        $store->addTurn('user', 'I prefer PSR-12');

        $learner = new ProfileLearner($store);
        $insights = $learner->extractInsights();

        self::assertCount(1, $insights);
    }

    public function testFormatAsProfilePatch(): void
    {
        $store = new ShortTermStore();
        $store->addTurn('user', 'I prefer dark themes');
        $store->addTurn('user', 'We use Docker for everything');

        $learner = new ProfileLearner($store);
        $insights = $learner->extractInsights();
        $patch = $learner->formatAsProfilePatch('Johannes', $insights);

        self::assertStringContainsString('Proposed updates for Johannes', $patch);
        self::assertStringContainsString('Preferences', $patch);
        self::assertStringContainsString('Tech Context', $patch);
    }

    public function testEmptySessionReturnsNoInsights(): void
    {
        $store = new ShortTermStore();
        $learner = new ProfileLearner($store);

        self::assertCount(0, $learner->extractInsights());
        self::assertSame('', $learner->formatAsProfilePatch('Test', []));
    }
}
