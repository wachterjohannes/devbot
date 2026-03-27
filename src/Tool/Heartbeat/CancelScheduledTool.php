<?php

declare(strict_types=1);

namespace App\Tool\Heartbeat;

use App\Heartbeat\ScheduledTaskManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Cancel a scheduled task.
 */
#[AsTool('cancel_scheduled', 'Cancel a scheduled task or reminder by ID.')]
final readonly class CancelScheduledTool
{
    public function __construct(
        private ScheduledTaskManager $scheduledTaskManager,
    ) {
    }

    /**
     * @param string $id The scheduled task ID to cancel
     *
     * @return array{cancelled: bool}
     */
    public function __invoke(string $id): array
    {
        return ['cancelled' => $this->scheduledTaskManager->remove($id)];
    }
}
