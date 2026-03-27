<?php

declare(strict_types=1);

namespace App\Skill;

use App\Skill\Model\Skill;
use App\Skill\Model\SkillTrigger;

/**
 * CRUD for skill definitions. Skills are markdown files with a JSON index registry.
 */
final class SkillManager
{
    private const INDEX_FILE = 'index.json';

    /** @var array<string, array<string, mixed>> */
    private array $index = [];

    public function __construct(
        private readonly string $skillsDir,
        private readonly SkillParser $parser,
    ) {
        $this->loadIndex();
    }

    /**
     * Create a new skill from a description. The agent generates the markdown.
     *
     * @param list<string>                                                   $steps
     * @param array<string, array{type: string, required: bool, default?: mixed}> $parameters
     */
    public function create(
        string $name,
        string $description,
        SkillTrigger $trigger = SkillTrigger::MANUAL,
        ?string $schedule = null,
        array $steps = [],
        array $parameters = [],
    ): Skill {
        $id = $this->slugify($name);
        $skill = new Skill(
            id: $id,
            name: $name,
            description: $description,
            trigger: $trigger,
            schedule: $schedule,
            parameters: $parameters,
            steps: $steps,
        );

        $this->saveSkill($skill);

        return $skill;
    }

    /**
     * Save a skill (create or update).
     */
    public function saveSkill(Skill $skill): void
    {
        $markdown = $this->parser->toMarkdown($skill);
        $filePath = $this->skillsDir . '/' . $skill->id . '.md';

        if (!is_dir($this->skillsDir)) {
            mkdir($this->skillsDir, 0755, true);
        }

        file_put_contents($filePath, $markdown);

        $this->index[$skill->id] = $skill->jsonSerialize();
        $this->saveIndex();
    }

    public function get(string $id): ?Skill
    {
        $filePath = $this->skillsDir . '/' . $id . '.md';

        if (!is_file($filePath)) {
            return null;
        }

        $markdown = file_get_contents($filePath) ?: '';
        $skill = $this->parser->parse($id, $markdown);

        // Restore runtime state from index
        $meta = $this->index[$id] ?? [];
        $skill->enabled = $meta['enabled'] ?? true;
        if (isset($meta['last_run'])) {
            $skill->lastRun = new \DateTimeImmutable($meta['last_run']);
        }

        return $skill;
    }

    /**
     * @return list<Skill>
     */
    public function list(): array
    {
        $skills = [];

        foreach (array_keys($this->index) as $id) {
            $skill = $this->get($id);
            if ($skill !== null) {
                $skills[] = $skill;
            }
        }

        return $skills;
    }

    /**
     * @return list<Skill> Skills with cron/interval triggers that are enabled
     */
    public function getScheduledSkills(): array
    {
        return array_values(array_filter(
            $this->list(),
            static fn (Skill $s) => $s->enabled
                && \in_array($s->trigger, [SkillTrigger::CRON, SkillTrigger::INTERVAL], true)
                && $s->schedule !== null,
        ));
    }

    public function toggle(string $id, bool $enabled): bool
    {
        if (!isset($this->index[$id])) {
            return false;
        }

        $this->index[$id]['enabled'] = $enabled;
        $this->saveIndex();

        return true;
    }

    public function markRun(string $id): void
    {
        if (isset($this->index[$id])) {
            $this->index[$id]['last_run'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            $this->saveIndex();
        }
    }

    public function delete(string $id): bool
    {
        $filePath = $this->skillsDir . '/' . $id . '.md';

        if (!is_file($filePath)) {
            return false;
        }

        // Move to archive
        $archiveDir = $this->skillsDir . '/archive';
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }

        rename($filePath, $archiveDir . '/' . $id . '.md');
        unset($this->index[$id]);
        $this->saveIndex();

        return true;
    }

    private function slugify(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;

        return trim($slug, '-');
    }

    private function loadIndex(): void
    {
        $path = $this->skillsDir . '/' . self::INDEX_FILE;

        if (!is_file($path)) {
            return;
        }

        $json = file_get_contents($path) ?: '{}';
        $this->index = json_decode($json, true) ?: [];
    }

    private function saveIndex(): void
    {
        if (!is_dir($this->skillsDir)) {
            mkdir($this->skillsDir, 0755, true);
        }

        file_put_contents(
            $this->skillsDir . '/' . self::INDEX_FILE,
            json_encode($this->index, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
        );
    }
}
