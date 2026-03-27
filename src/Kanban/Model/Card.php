<?php

declare(strict_types=1);

namespace App\Kanban\Model;

/**
 * A task card on the kanban board.
 */
final class Card implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public string $title,
        public string $description = '',
        public CardStatus $status = CardStatus::BACKLOG,
        /** @var list<string> */
        public array $labels = [],
        public ?string $assignee = null,
        public string $priority = 'medium',
        /** @var list<array{title: string, done: bool}> */
        public array $subtasks = [],
        /** @var array<string, string|null> */
        public array $externalLinks = [],
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public \DateTimeImmutable $updatedAt = new \DateTimeImmutable(),
    ) {
    }

    public function moveTo(CardStatus $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'labels' => $this->labels,
            'assignee' => $this->assignee,
            'priority' => $this->priority,
            'subtasks' => $this->subtasks,
            'external_links' => $this->externalLinks,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            description: $data['description'] ?? '',
            status: CardStatus::from($data['status'] ?? 'backlog'),
            labels: $data['labels'] ?? [],
            assignee: $data['assignee'] ?? null,
            priority: $data['priority'] ?? 'medium',
            subtasks: $data['subtasks'] ?? [],
            externalLinks: $data['external_links'] ?? [],
            createdAt: new \DateTimeImmutable($data['created_at'] ?? 'now'),
            updatedAt: new \DateTimeImmutable($data['updated_at'] ?? 'now'),
        );
    }
}
