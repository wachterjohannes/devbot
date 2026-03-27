<?php

declare(strict_types=1);

namespace App\Skill;

use App\Skill\Model\Skill;
use App\Skill\Model\SkillTrigger;

/**
 * Parses skill markdown files into structured Skill models.
 *
 * Expected format:
 * ```markdown
 * # Skill: skill-name
 *
 * ## Description
 * What this skill does.
 *
 * ## Trigger
 * manual | cron: <expression> | interval: <seconds>
 *
 * ## Parameters
 * - param_name: type (required|optional) — description
 *
 * ## Steps
 * 1. First step
 * 2. Second step
 * ```
 */
final class SkillParser
{
    public function parse(string $id, string $markdown): Skill
    {
        $sections = $this->extractSections($markdown);

        $name = $this->extractName($markdown) ?? $id;
        $description = trim($sections['description'] ?? '');
        [$trigger, $schedule] = $this->parseTrigger($sections['trigger'] ?? 'manual');
        $parameters = $this->parseParameters($sections['parameters'] ?? '');
        $steps = $this->parseSteps($sections['steps'] ?? '');

        return new Skill(
            id: $id,
            name: $name,
            description: $description,
            trigger: $trigger,
            schedule: $schedule,
            parameters: $parameters,
            steps: $steps,
        );
    }

    /**
     * Generate markdown from a Skill model.
     */
    public function toMarkdown(Skill $skill): string
    {
        $md = "# Skill: {$skill->name}\n\n";
        $md .= "## Description\n{$skill->description}\n\n";

        $triggerLine = match ($skill->trigger) {
            SkillTrigger::CRON => "cron: {$skill->schedule}",
            SkillTrigger::INTERVAL => "interval: {$skill->schedule}",
            SkillTrigger::EVENT => "event: {$skill->schedule}",
            SkillTrigger::MANUAL => 'manual',
        };
        $md .= "## Trigger\n{$triggerLine}\n\n";

        if ($skill->parameters !== []) {
            $md .= "## Parameters\n";
            foreach ($skill->parameters as $name => $param) {
                $req = $param['required'] ? 'required' : 'optional';
                $md .= "- {$name}: {$param['type']} ({$req})\n";
            }
            $md .= "\n";
        }

        if ($skill->steps !== []) {
            $md .= "## Steps\n";
            foreach ($skill->steps as $i => $step) {
                $md .= ($i + 1) . ". {$step}\n";
            }
        }

        return $md;
    }

    /**
     * @return array<string, string>
     */
    private function extractSections(string $markdown): array
    {
        $sections = [];
        $currentSection = null;
        $currentContent = [];

        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^##\s+(.+)$/', $line, $m)) {
                if ($currentSection !== null) {
                    $sections[$currentSection] = implode("\n", $currentContent);
                }
                $currentSection = strtolower(trim($m[1]));
                $currentContent = [];
            } elseif ($currentSection !== null) {
                $currentContent[] = $line;
            }
        }

        if ($currentSection !== null) {
            $sections[$currentSection] = implode("\n", $currentContent);
        }

        return $sections;
    }

    private function extractName(string $markdown): ?string
    {
        if (preg_match('/^#\s+Skill:\s*(.+)$/m', $markdown, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * @return array{SkillTrigger, string|null}
     */
    private function parseTrigger(string $raw): array
    {
        $raw = trim($raw);

        if (preg_match('/^cron:\s*(.+)$/i', $raw, $m)) {
            return [SkillTrigger::CRON, trim($m[1])];
        }

        if (preg_match('/^interval:\s*(\d+)/i', $raw, $m)) {
            return [SkillTrigger::INTERVAL, $m[1]];
        }

        if (preg_match('/^event:\s*(.+)$/i', $raw, $m)) {
            return [SkillTrigger::EVENT, trim($m[1])];
        }

        return [SkillTrigger::MANUAL, null];
    }

    /**
     * @return array<string, array{type: string, required: bool}>
     */
    private function parseParameters(string $raw): array
    {
        $params = [];

        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^-\s+(\w+):\s+(\w+)\s+\((\w+)\)/', $line, $m)) {
                $params[$m[1]] = [
                    'type' => $m[2],
                    'required' => $m[3] === 'required',
                ];
            }
        }

        return $params;
    }

    /**
     * @return list<string>
     */
    private function parseSteps(string $raw): array
    {
        $steps = [];

        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
                $steps[] = trim($m[1]);
            }
        }

        return $steps;
    }
}
