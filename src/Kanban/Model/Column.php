<?php

declare(strict_types=1);

namespace App\Kanban\Model;

/**
 * A kanban board column with optional WIP limit.
 */
final readonly class Column implements \JsonSerializable
{
    public function __construct(
        public CardStatus $status,
        public string $name,
        public ?int $wipLimit = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->status->value,
            'name' => $this->name,
            'wip_limit' => $this->wipLimit,
        ];
    }
}
