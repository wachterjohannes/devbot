<?php

declare(strict_types=1);

namespace App\Tests\Unit\Skill;

use App\Skill\Model\SkillTrigger;
use App\Skill\SkillParser;
use PHPUnit\Framework\TestCase;

final class SkillParserTest extends TestCase
{
    private SkillParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SkillParser();
    }

    public function testParseFullSkill(): void
    {
        $md = <<<'MD'
# Skill: daily-news-digest

## Description
Search the web for PHP news and send a summary.

## Trigger
cron: 0 9 * * *

## Parameters
- topic: string (required)
- max_results: int (optional)

## Steps
1. Use `web_search` to find recent PHP news
2. Summarize the results
3. Store summary in memory
MD;

        $skill = $this->parser->parse('daily-news-digest', $md);

        self::assertSame('daily-news-digest', $skill->id);
        self::assertSame('daily-news-digest', $skill->name);
        self::assertStringContainsString('Search the web', $skill->description);
        self::assertSame(SkillTrigger::CRON, $skill->trigger);
        self::assertSame('0 9 * * *', $skill->schedule);
        self::assertCount(2, $skill->parameters);
        self::assertTrue($skill->parameters['topic']['required']);
        self::assertFalse($skill->parameters['max_results']['required']);
        self::assertCount(3, $skill->steps);
        self::assertStringContainsString('web_search', $skill->steps[0]);
    }

    public function testParseManualTrigger(): void
    {
        $md = "# Skill: test\n\n## Description\nTest\n\n## Trigger\nmanual\n\n## Steps\n1. Do something";
        $skill = $this->parser->parse('test', $md);

        self::assertSame(SkillTrigger::MANUAL, $skill->trigger);
        self::assertNull($skill->schedule);
    }

    public function testParseIntervalTrigger(): void
    {
        $md = "# Skill: poller\n\n## Description\nPoll\n\n## Trigger\ninterval: 900\n\n## Steps\n1. Check something";
        $skill = $this->parser->parse('poller', $md);

        self::assertSame(SkillTrigger::INTERVAL, $skill->trigger);
        self::assertSame('900', $skill->schedule);
    }

    public function testToMarkdownRoundTrip(): void
    {
        $md = <<<'MD'
# Skill: roundtrip-test

## Description
A test skill.

## Trigger
cron: 30 8 * * 1

## Parameters
- query: string (required)

## Steps
1. First step
2. Second step
MD;

        $skill = $this->parser->parse('roundtrip-test', $md);
        $output = $this->parser->toMarkdown($skill);
        $reparsed = $this->parser->parse('roundtrip-test', $output);

        self::assertSame($skill->name, $reparsed->name);
        self::assertSame($skill->trigger, $reparsed->trigger);
        self::assertSame($skill->schedule, $reparsed->schedule);
        self::assertCount(\count($skill->steps), $reparsed->steps);
        self::assertCount(\count($skill->parameters), $reparsed->parameters);
    }
}
