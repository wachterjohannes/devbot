<?php

declare(strict_types=1);

namespace App\Agent\Processor;

use App\Memory\MemoryManager;
use Symfony\AI\Agent\Attribute\AsInputProcessor;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Auto-injects relevant memories into the agent's context before each call.
 * Uses a lightweight semantic search to find baseline context without the agent
 * explicitly searching. The agent can still use memory tools for deeper exploration.
 */
#[AsInputProcessor(agent: 'ai.agent.devbot', priority: -40)]
final readonly class MemoryInjectionProcessor implements InputProcessorInterface
{
    public function __construct(
        private MemoryManager $memoryManager,
        private int $maxInjectedEntries = 3,
    ) {
    }

    public function processInput(Input $input): void
    {
        $query = $this->extractLatestUserText($input);

        if ($query === '') {
            return;
        }

        try {
            $results = $this->memoryManager->semanticSearch($query, $this->maxInjectedEntries);
        } catch (\Throwable) {
            // Semantic store might not be set up yet — gracefully skip
            return;
        }

        if ($results === []) {
            return;
        }

        $contextLines = ["# Relevant Memory Context\n"];
        foreach ($results as $result) {
            $score = round($result['score'], 3);
            $contextLines[] = "- **[{$result['id']}]** (relevance: {$score}): {$result['snippet']}";
        }

        $contextLines[] = "\n_Use memory_search/memory_grep/memory_read tools for deeper exploration._";
        $contextBlock = implode("\n", $contextLines);

        $messageBag = $input->getMessageBag();
        $existing = $messageBag->getSystemMessage();

        if ($existing !== null) {
            $existingContent = $existing->getContent();
            $newPrompt = ($existingContent instanceof \Stringable ? (string) $existingContent : $existingContent) . "\n\n" . $contextBlock;
            $input->setMessageBag(
                $messageBag->withSystemMessage(Message::forSystem($newPrompt)),
            );
        }
    }

    private function extractLatestUserText(Input $input): string
    {
        $messages = $input->getMessageBag()->getMessages();

        for ($i = \count($messages) - 1; $i >= 0; --$i) {
            if ($messages[$i] instanceof UserMessage) {
                foreach ($messages[$i]->getContent() as $content) {
                    if ($content instanceof Text) {
                        return $content->getText();
                    }
                }
            }
        }

        return '';
    }
}
