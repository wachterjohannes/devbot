<?php

declare(strict_types=1);

namespace App\Agent\Processor;

use App\Identity\IdentityManager;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Platform\Message\Message;

/**
 * Injects SOUL, IDENTITY, and active human profile into the agent's system context.
 * Runs before every agent call to give the bot its personality and awareness.
 */
final readonly class IdentityInjectionProcessor implements InputProcessorInterface
{
    public function __construct(
        private IdentityManager $identityManager,
    ) {
    }

    public function processInput(Input $input): void
    {
        $parts = [];

        $soul = $this->identityManager->loadSoul();
        if ($soul->content !== '') {
            $parts[] = "## Your Soul (Personality)\n\n" . $soul->content;
        }

        $identity = $this->identityManager->loadIdentity();
        if ($identity->content !== '') {
            $parts[] = "## Your Identity\n\n" . $identity->content;
        }

        $profiles = $this->identityManager->loadHumanProfiles();
        foreach ($profiles as $profile) {
            $parts[] = "## Human Profile: {$profile->name}\n\n" . $profile->content;
        }

        if ($parts === []) {
            return;
        }

        $contextBlock = "# Identity Context\n\n" . implode("\n\n---\n\n", $parts);

        $messageBag = $input->getMessageBag();
        $input->setMessageBag(
            $messageBag->withSystemMessage(Message::forSystem($contextBlock)),
        );
    }
}
