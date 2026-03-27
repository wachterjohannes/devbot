<?php

declare(strict_types=1);

namespace App\Memory\Model;

/**
 * Metadata attached to every memory entry.
 * Tracks importance, access patterns, tags, and provenance.
 */
final class MemoryMetadata implements \JsonSerializable
{
    public function __construct(
        public float $importance = 0.5,
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public \DateTimeImmutable $lastAccessedAt = new \DateTimeImmutable(),
        public int $accessCount = 0,
        /** @var list<string> */
        public array $tags = [],
        public ?string $source = null,
        public ?string $topic = null,
    ) {
    }

    public function touch(): void
    {
        $this->lastAccessedAt = new \DateTimeImmutable();
        ++$this->accessCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'importance' => $this->importance,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'last_accessed_at' => $this->lastAccessedAt->format(\DateTimeInterface::ATOM),
            'access_count' => $this->accessCount,
            'tags' => $this->tags,
            'source' => $this->source,
            'topic' => $this->topic,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            importance: (float) ($data['importance'] ?? 0.5),
            createdAt: new \DateTimeImmutable($data['created_at'] ?? 'now'),
            lastAccessedAt: new \DateTimeImmutable($data['last_accessed_at'] ?? 'now'),
            accessCount: (int) ($data['access_count'] ?? 0),
            tags: $data['tags'] ?? [],
            source: $data['source'] ?? null,
            topic: $data['topic'] ?? null,
        );
    }
}
