<?php

declare(strict_types=1);

namespace App\Skill;

use App\Memory\MemoryManager;
use App\Skill\Model\Skill;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Executes a skill by building a prompt from its steps and calling the agent.
 * The agent IS the runtime — skills are just structured prompts with tool instructions.
 */
final readonly class SkillRunner
{
    public function __construct(
        private AgentInterface $agent,
        private SkillManager $skillManager,
        private MemoryManager $memoryManager,
    ) {
    }

    /**
     * Run a skill by ID with optional parameters.
     *
     * @param array<string, mixed> $params
     */
    public function run(string $skillId, array $params = []): string
    {
        $skill = $this->skillManager->get($skillId);

        if ($skill === null) {
            return "Skill '{$skillId}' not found.";
        }

        if (!$skill->enabled) {
            return "Skill '{$skill->name}' is disabled.";
        }

        return $this->execute($skill, $params);
    }

    /**
     * Execute a Skill model directly.
     *
     * @param array<string, mixed> $params
     */
    public function execute(Skill $skill, array $params = []): string
    {
        $prompt = $skill->buildPrompt($params);

        $messages = new MessageBag(
            Message::forSystem("You are executing a scheduled skill. Follow the steps precisely using the available tools."),
            Message::ofUser($prompt),
        );

        try {
            $result = $this->agent->call($messages);
            $response = $result->getContent();
            $content = \is_string($response) ? $response : '(non-text response)';
        } catch (\Throwable $e) {
            $content = 'Skill execution failed: ' . $e->getMessage();
        }

        // Log execution to episodic memory
        $this->memoryManager->logEvent(
            "Executed skill '{$skill->name}': {$content}",
            tags: ['skill', $skill->id],
            importance: 0.4,
            source: 'heartbeat',
        );

        $this->skillManager->markRun($skill->id);

        return $content;
    }
}
