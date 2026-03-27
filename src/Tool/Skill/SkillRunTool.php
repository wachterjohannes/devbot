<?php

declare(strict_types=1);

namespace App\Tool\Skill;

use App\Skill\SkillRunner;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Execute a skill immediately.
 */
#[AsTool('skill_run', 'Execute a skill immediately by ID. The skill steps are run using all available tools.')]
final readonly class SkillRunTool
{
    public function __construct(
        private SkillRunner $skillRunner,
    ) {
    }

    /**
     * @param string $id Skill ID to execute
     *
     * @return array{result: string}
     */
    public function __invoke(string $id): array
    {
        return ['result' => $this->skillRunner->run($id)];
    }
}
