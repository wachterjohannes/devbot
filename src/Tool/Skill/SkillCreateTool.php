<?php

declare(strict_types=1);

namespace App\Tool\Skill;

use App\Skill\Model\SkillTrigger;
use App\Skill\SkillManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Create a new skill from a description.
 */
#[AsTool('skill_create', 'Create a new reusable skill/workflow. Specify steps, trigger, and parameters.')]
final readonly class SkillCreateTool
{
    public function __construct(
        private SkillManager $skillManager,
    ) {
    }

    /**
     * @param string       $name        Skill name (e.g. "daily-standup-summary")
     * @param string       $description What the skill does
     * @param list<string> $steps       Ordered steps the agent should execute
     * @param string       $trigger     Trigger type: "manual", "cron", "interval"
     * @param string|null  $schedule    Cron expression or interval in seconds
     *
     * @return array{id: string, name: string, trigger: string, created: true}
     */
    public function __invoke(
        string $name,
        string $description,
        array $steps = [],
        string $trigger = 'manual',
        ?string $schedule = null,
    ): array {
        $triggerType = SkillTrigger::tryFrom($trigger) ?? SkillTrigger::MANUAL;

        $skill = $this->skillManager->create(
            name: $name,
            description: $description,
            trigger: $triggerType,
            schedule: $schedule,
            steps: $steps,
        );

        return [
            'id' => $skill->id,
            'name' => $skill->name,
            'trigger' => $skill->trigger->value,
            'created' => true,
        ];
    }
}
