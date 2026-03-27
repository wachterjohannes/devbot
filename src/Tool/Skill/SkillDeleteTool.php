<?php

declare(strict_types=1);

namespace App\Tool\Skill;

use App\Skill\SkillManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Delete/archive a skill.
 */
#[AsTool('skill_delete', 'Delete a skill by ID. The skill file is moved to the archive.')]
final readonly class SkillDeleteTool
{
    public function __construct(
        private SkillManager $skillManager,
    ) {
    }

    /**
     * @param string $id Skill ID to delete
     *
     * @return array{deleted: bool}
     */
    public function __invoke(string $id): array
    {
        return ['deleted' => $this->skillManager->delete($id)];
    }
}
