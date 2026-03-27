<?php

declare(strict_types=1);

namespace App\Tool\Skill;

use App\Skill\SkillManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * List all skills with their status and trigger info.
 */
#[AsTool('skill_list', 'List all skills with their status, trigger type, and last run time.')]
final readonly class SkillListTool
{
    public function __construct(
        private SkillManager $skillManager,
    ) {
    }

    /**
     * @return array<int, array{id: string, name: string, trigger: string, schedule: string|null, enabled: bool, last_run: string|null}>
     */
    public function __invoke(): array
    {
        return array_map(
            static fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'trigger' => $skill->trigger->value,
                'schedule' => $skill->schedule,
                'enabled' => $skill->enabled,
                'last_run' => $skill->lastRun?->format(\DateTimeInterface::ATOM),
            ],
            $this->skillManager->list(),
        );
    }
}
