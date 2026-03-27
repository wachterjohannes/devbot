<?php

declare(strict_types=1);

namespace App\Identity\Model;

/**
 * Parsed representation of a human profile from identity/humans/*.md.
 */
final readonly class HumanProfile
{
    public function __construct(
        public string $name,
        public string $content,
    ) {
    }
}
