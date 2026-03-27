<?php

declare(strict_types=1);

namespace App\Heartbeat\Model;

/**
 * A one-off scheduled task (reminder, research, notification).
 * Removed after execution.
 */
final class ScheduledTask implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly \DateTimeImmutable $runAt,
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
    }

    public function isDue(): bool
    {
        return new \DateTimeImmutable() >= $this->runAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'run_at' => $this->runAt->format(\DateTimeInterface::ATOM),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            description: $data['description'],
            runAt: new \DateTimeImmutable($data['run_at']),
            createdAt: new \DateTimeImmutable($data['created_at'] ?? 'now'),
        );
    }
}
