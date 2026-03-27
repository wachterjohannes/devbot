<?php

declare(strict_types=1);

namespace App\Memory\Model;

/**
 * A single memory entry across any tier.
 * Immutable ID and type; content and metadata are mutable for updates.
 */
final class MemoryEntry implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public string $content,
        public readonly MemoryType $type,
        public MemoryMetadata $metadata = new MemoryMetadata(),
    ) {
    }

    /**
     * Short preview of content for search result display.
     */
    public function getSnippet(int $maxLength = 200): string
    {
        $text = str_replace("\n", ' ', $this->content);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . '...';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'type' => $this->type->value,
            'metadata' => $this->metadata->jsonSerialize(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            content: $data['content'],
            type: MemoryType::from($data['type']),
            metadata: MemoryMetadata::fromArray($data['metadata'] ?? []),
        );
    }
}
