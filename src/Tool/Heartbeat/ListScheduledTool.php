<?php

declare(strict_types=1);

namespace App\Tool\Heartbeat;

use App\Heartbeat\ScheduledTaskManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * List upcoming scheduled tasks.
 */
#[AsTool('list_scheduled', 'List all upcoming scheduled tasks and reminders.')]
final readonly class ListScheduledTool
{
    public function __construct(
        private ScheduledTaskManager $scheduledTaskManager,
    ) {
    }

    /**
     * @return array<int, array{id: string, description: string, run_at: string}>
     */
    public function __invoke(): array
    {
        return array_map(
            static fn ($task) => [
                'id' => $task->id,
                'description' => $task->description,
                'run_at' => $task->runAt->format(\DateTimeInterface::ATOM),
            ],
            $this->scheduledTaskManager->getAll(),
        );
    }
}
