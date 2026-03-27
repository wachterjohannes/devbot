<?php

declare(strict_types=1);

namespace App\Identity\Model;

/**
 * Parsed representation of IDENTITY.md — bot self-knowledge and capabilities.
 */
final readonly class Identity
{
    public function __construct(
        public string $content,
    ) {
    }
}
