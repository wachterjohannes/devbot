<?php

declare(strict_types=1);

namespace App\Tool\Skill;

use App\Skill\SkillManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Enable or disable a skill.
 */
#[AsTool('skill_toggle', 'Enable or disable a skill by ID.')]
final readonly class SkillToggleTool
{
    public function __construct(
        private SkillManager $skillManager,
    ) {
    }

    /**
     * @param string $id      Skill ID
     * @param bool   $enabled True to enable, false to disable
     *
     * @return array{toggled: bool, enabled: bool}
     */
    public function __invoke(string $id, bool $enabled): array
    {
        $result = $this->skillManager->toggle($id, $enabled);

        return [
            'toggled' => $result,
            'enabled' => $enabled,
        ];
    }
}
