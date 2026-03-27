<?php

declare(strict_types=1);

namespace App\Identity\Model;

/**
 * Parsed representation of SOUL.md — bot personality and values.
 */
final readonly class Soul
{
    public function __construct(
        public string $content,
    ) {
    }
}
