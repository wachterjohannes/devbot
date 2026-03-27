<?php

declare(strict_types=1);

namespace App\Tests\Unit\Memory;

use App\Memory\Strategy\RuleBasedImportanceScorer;
use PHPUnit\Framework\TestCase;

final class ImportanceScorerTest extends TestCase
{
    private RuleBasedImportanceScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new RuleBasedImportanceScorer();
    }

    public function testBaselineScore(): void
    {
        $score = $this->scorer->score('Just a normal sentence about nothing special.');

        self::assertGreaterThan(0.0, $score);
        self::assertLessThan(1.0, $score);
    }

    public function testDecisionBoosted(): void
    {
        $normal = $this->scorer->score('We talked about stuff.');
        $decision = $this->scorer->score('We decided to use PostgreSQL for the database.');

        self::assertGreaterThan($normal, $decision);
    }

    public function testSecretsPenalized(): void
    {
        $normal = $this->scorer->score('The config is in settings.yaml');
        $secret = $this->scorer->score('The api_key is sk-12345 and the password is hunter2');

        self::assertLessThan($normal, $secret);
    }

    public function testScoreClamped(): void
    {
        // Very short content
        $score = $this->scorer->score('hi');
        self::assertGreaterThanOrEqual(0.0, $score);

        // Content with many boosts
        $score = $this->scorer->score('Critical security decision: always remember to never deploy without review. Important architecture pattern.');
        self::assertLessThanOrEqual(1.0, $score);
    }
}
