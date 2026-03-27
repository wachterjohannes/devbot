<?php

declare(strict_types=1);

namespace App\Skill\Model;

/**
 * A skill definition: a reusable workflow the bot can execute.
 * Parsed from markdown files in skills/.
 */
final class Skill implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public string $description,
        public SkillTrigger $trigger = SkillTrigger::MANUAL,
        public ?string $schedule = null,
        /** @var array<string, array{type: string, required: bool, default?: mixed}> */
        public array $parameters = [],
        /** @var list<string> */
        public array $steps = [],
        public bool $enabled = true,
        public ?\DateTimeImmutable $lastRun = null,
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
    }

    /**
     * Build the full prompt the agent should execute.
     *
     * @param array<string, mixed> $params Runtime parameter values
     */
    public function buildPrompt(array $params = []): string
    {
        $prompt = "# Executing Skill: {$this->name}\n\n";
        $prompt .= "{$this->description}\n\n";

        if ($params !== []) {
            $prompt .= "## Parameters\n\n";
            foreach ($params as $key => $value) {
                $prompt .= "- **{$key}**: " . (is_string($value) ? $value : json_encode($value)) . "\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "## Steps\n\n";
        foreach ($this->steps as $i => $step) {
            $prompt .= ($i + 1) . ". {$step}\n";
        }

        $prompt .= "\nExecute each step in order using the available tools. Report results after completion.";

        return $prompt;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'trigger' => $this->trigger->value,
            'schedule' => $this->schedule,
            'parameters' => $this->parameters,
            'enabled' => $this->enabled,
            'last_run' => $this->lastRun?->format(\DateTimeInterface::ATOM),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
