<?php

declare(strict_types=1);

namespace App\Heartbeat;

use App\Heartbeat\Model\ScheduledTask;
use App\Memory\MemoryManager;
use App\Skill\Model\Skill;
use App\Skill\SkillRunner;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Executes due skills and scheduled tasks via the agent.
 */
final readonly class TaskExecutor
{
    public function __construct(
        private SkillRunner $skillRunner,
        private ScheduledTaskManager $scheduledTaskManager,
        private MemoryManager $memoryManager,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Execute a skill that is due.
     */
    public function executeSkill(Skill $skill): string
    {
        $this->logger->info('Heartbeat: executing skill "{name}"', ['name' => $skill->name]);

        return $this->skillRunner->execute($skill);
    }

    /**
     * Execute a one-off scheduled task. Logs to memory and removes from schedule.
     */
    public function executeScheduledTask(ScheduledTask $task): string
    {
        $this->logger->info('Heartbeat: executing scheduled task "{desc}"', ['desc' => $task->description]);

        // Log the reminder/task as an episodic event
        $this->memoryManager->logEvent(
            "Scheduled task executed: {$task->description}",
            tags: ['scheduled', 'heartbeat'],
            importance: 0.5,
            source: 'heartbeat',
        );

        // Remove the one-off task
        $this->scheduledTaskManager->remove($task->id);

        return "Scheduled task completed: {$task->description}";
    }
}
