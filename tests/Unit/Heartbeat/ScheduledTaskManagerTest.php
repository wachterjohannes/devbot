<?php

declare(strict_types=1);

namespace App\Tests\Unit\Heartbeat;

use App\Heartbeat\ScheduledTaskManager;
use PHPUnit\Framework\TestCase;

final class ScheduledTaskManagerTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/devbot_sched_test_' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testScheduleAndList(): void
    {
        $manager = new ScheduledTaskManager($this->tmpFile);
        $task = $manager->schedule('Review the PR', new \DateTimeImmutable('+1 hour'));

        self::assertStringStartsWith('sched-', $task->id);
        self::assertCount(1, $manager->getAll());
    }

    public function testGetDueTasks(): void
    {
        $manager = new ScheduledTaskManager($this->tmpFile);
        $manager->schedule('Past task', new \DateTimeImmutable('-1 hour'));
        $manager->schedule('Future task', new \DateTimeImmutable('+1 hour'));

        $due = $manager->getDueTasks();
        self::assertCount(1, $due);
        self::assertSame('Past task', $due[0]->description);
    }

    public function testRemove(): void
    {
        $manager = new ScheduledTaskManager($this->tmpFile);
        $task = $manager->schedule('Cancel me', new \DateTimeImmutable('+1 hour'));

        self::assertTrue($manager->remove($task->id));
        self::assertCount(0, $manager->getAll());
        self::assertFalse($manager->remove('nonexistent'));
    }

    public function testPersistence(): void
    {
        $manager = new ScheduledTaskManager($this->tmpFile);
        $manager->schedule('Persistent', new \DateTimeImmutable('+1 hour'));

        $manager2 = new ScheduledTaskManager($this->tmpFile);
        self::assertCount(1, $manager2->getAll());
        self::assertSame('Persistent', $manager2->getAll()[0]->description);
    }
}
