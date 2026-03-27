<?php

declare(strict_types=1);

namespace App\Tests\Unit\Skill;

use App\Skill\Model\SkillTrigger;
use App\Skill\SkillManager;
use App\Skill\SkillParser;
use PHPUnit\Framework\TestCase;

final class SkillManagerTest extends TestCase
{
    private string $tmpDir;
    private SkillManager $manager;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/devbot_skills_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0755, true);
        $this->manager = new SkillManager($this->tmpDir, new SkillParser());
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testCreateAndGet(): void
    {
        $skill = $this->manager->create(
            name: 'PHP News Digest',
            description: 'Search for PHP news daily',
            trigger: SkillTrigger::CRON,
            schedule: '0 9 * * *',
            steps: ['Search the web', 'Summarize results', 'Store in memory'],
        );

        self::assertSame('php-news-digest', $skill->id);
        self::assertFileExists($this->tmpDir . '/php-news-digest.md');

        $retrieved = $this->manager->get('php-news-digest');
        self::assertNotNull($retrieved);
        self::assertSame('PHP News Digest', $retrieved->name);
        self::assertSame(SkillTrigger::CRON, $retrieved->trigger);
        self::assertCount(3, $retrieved->steps);
    }

    public function testList(): void
    {
        $this->manager->create('Skill A', 'First');
        $this->manager->create('Skill B', 'Second');

        $skills = $this->manager->list();
        self::assertCount(2, $skills);
    }

    public function testToggle(): void
    {
        $this->manager->create('Toggle Test', 'Test');

        self::assertTrue($this->manager->toggle('toggle-test', false));
        $skill = $this->manager->get('toggle-test');
        self::assertFalse($skill?->enabled);

        self::assertTrue($this->manager->toggle('toggle-test', true));
        $skill = $this->manager->get('toggle-test');
        self::assertTrue($skill?->enabled);
    }

    public function testDelete(): void
    {
        $this->manager->create('Delete Me', 'Will be deleted');

        self::assertTrue($this->manager->delete('delete-me'));
        self::assertNull($this->manager->get('delete-me'));
        self::assertFileExists($this->tmpDir . '/archive/delete-me.md');
        self::assertFalse($this->manager->delete('nonexistent'));
    }

    public function testGetScheduledSkills(): void
    {
        $this->manager->create('Manual', 'Manual skill');
        $this->manager->create('Cron', 'Cron skill', SkillTrigger::CRON, '0 9 * * *', ['Step']);
        $this->manager->create('Interval', 'Interval skill', SkillTrigger::INTERVAL, '900', ['Step']);

        $scheduled = $this->manager->getScheduledSkills();
        self::assertCount(2, $scheduled);
    }

    public function testMarkRun(): void
    {
        $this->manager->create('Run Test', 'Test');
        $this->manager->markRun('run-test');

        $skill = $this->manager->get('run-test');
        self::assertNotNull($skill?->lastRun);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
