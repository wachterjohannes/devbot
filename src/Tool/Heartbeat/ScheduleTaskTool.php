<?php

declare(strict_types=1);

namespace App\Tool\Heartbeat;

use App\Heartbeat\ScheduledTaskManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Schedule a one-off task for later execution.
 */
#[AsTool('schedule_task', 'Schedule a one-off task or reminder for a specific time. E.g. "Remind me at 14:00 to deploy".')]
final readonly class ScheduleTaskTool
{
    public function __construct(
        private ScheduledTaskManager $scheduledTaskManager,
    ) {
    }

    /**
     * @param string $description What to do (e.g. "Review the PR", "Search for Symfony news")
     * @param string $runAt       When to run (ISO 8601 datetime or relative like "+2 hours")
     *
     * @return array{id: string, description: string, run_at: string, scheduled: true}
     */
    public function __invoke(string $description, string $runAt): array
    {
        $runAtTime = new \DateTimeImmutable($runAt);
        $task = $this->scheduledTaskManager->schedule($description, $runAtTime);

        return [
            'id' => $task->id,
            'description' => $task->description,
            'run_at' => $task->runAt->format(\DateTimeInterface::ATOM),
            'scheduled' => true,
        ];
    }
}
