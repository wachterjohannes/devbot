<?php

declare(strict_types=1);

namespace App\Agent\Processor;

use App\Agent\Prompt\ContextWindowManager;
use Symfony\AI\Agent\Attribute\AsInputProcessor;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;

/**
 * Truncates the conversation history to fit within the context window.
 * Runs last (lowest priority) after all other processors have added their context.
 */
#[AsInputProcessor(agent: 'ai.agent.devbot', priority: -50)]
final readonly class ContextTruncationProcessor implements InputProcessorInterface
{
    public function __construct(
        private ContextWindowManager $contextWindowManager,
    ) {
    }

    public function processInput(Input $input): void
    {
        $messageBag = $input->getMessageBag();

        if ($this->contextWindowManager->getUsageRatio($messageBag) > 0.8) {
            $input->setMessageBag(
                $this->contextWindowManager->truncate($messageBag),
            );
        }
    }
}
