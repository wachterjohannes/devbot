<?php

declare(strict_types=1);

namespace App\Tool\Skill;

use App\Skill\Model\SkillTrigger;
use App\Skill\SkillManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Update an existing skill definition.
 */
#[AsTool('skill_update', 'Update a skill definition. Can change description, steps, trigger, or schedule.')]
final readonly class SkillUpdateTool
{
    public function __construct(
        private SkillManager $skillManager,
    ) {
    }

    /**
     * @param string            $id          Skill ID
     * @param string|null       $description New description (null to keep)
     * @param list<string>|null $steps       New steps (null to keep)
     * @param string|null       $trigger     New trigger type (null to keep)
     * @param string|null       $schedule    New schedule (null to keep)
     *
     * @return array{updated: bool}
     */
    public function __invoke(
        string $id,
        ?string $description = null,
        ?array $steps = null,
        ?string $trigger = null,
        ?string $schedule = null,
    ): array {
        $skill = $this->skillManager->get($id);

        if ($skill === null) {
            return ['updated' => false];
        }

        if ($description !== null) {
            $skill->description = $description;
        }
        if ($steps !== null) {
            $skill->steps = $steps;
        }
        if ($trigger !== null) {
            $skill->trigger = SkillTrigger::tryFrom($trigger) ?? $skill->trigger;
        }
        if ($schedule !== null) {
            $skill->schedule = $schedule;
        }

        $this->skillManager->saveSkill($skill);

        return ['updated' => true];
    }
}
