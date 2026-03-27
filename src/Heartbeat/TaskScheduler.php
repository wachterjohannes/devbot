<?php

declare(strict_types=1);

namespace App\Heartbeat;

use App\Heartbeat\Model\ScheduledTask;
use App\Skill\Model\Skill;
use App\Skill\Model\SkillTrigger;
use App\Skill\SkillManager;

/**
 * Determines which skills and scheduled tasks are due for execution.
 */
final readonly class TaskScheduler
{
    public function __construct(
        private SkillManager $skillManager,
        private ScheduledTaskManager $scheduledTaskManager,
    ) {
    }

    /**
     * @return list<Skill> Skills that should run now
     */
    public function getDueSkills(): array
    {
        $due = [];

        foreach ($this->skillManager->getScheduledSkills() as $skill) {
            if ($this->isSkillDue($skill)) {
                $due[] = $skill;
            }
        }

        return $due;
    }

    /**
     * @return list<ScheduledTask> One-off tasks that are due
     */
    public function getDueScheduledTasks(): array
    {
        return $this->scheduledTaskManager->getDueTasks();
    }

    private function isSkillDue(Skill $skill): bool
    {
        if ($skill->trigger === SkillTrigger::INTERVAL) {
            $intervalSeconds = (int) ($skill->schedule ?? 0);
            if ($intervalSeconds <= 0) {
                return false;
            }

            if ($skill->lastRun === null) {
                return true;
            }

            $elapsed = (new \DateTimeImmutable())->getTimestamp() - $skill->lastRun->getTimestamp();

            return $elapsed >= $intervalSeconds;
        }

        if ($skill->trigger === SkillTrigger::CRON) {
            return $this->isCronDue($skill->schedule ?? '', $skill->lastRun);
        }

        return false;
    }

    /**
     * Simple cron check: only supports "M H * * *" format (minute + hour).
     */
    private function isCronDue(string $expression, ?\DateTimeImmutable $lastRun): bool
    {
        $parts = explode(' ', $expression);
        if (\count($parts) < 5) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $minute = $parts[0];
        $hour = $parts[1];

        // Check if current time matches
        if ($minute !== '*' && (int) $minute !== (int) $now->format('i')) {
            return false;
        }

        if ($hour !== '*' && (int) $hour !== (int) $now->format('G')) {
            return false;
        }

        // Don't run if already ran this minute
        if ($lastRun !== null && $lastRun->format('Y-m-d H:i') === $now->format('Y-m-d H:i')) {
            return false;
        }

        return true;
    }
}
