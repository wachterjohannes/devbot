<?php

declare(strict_types=1);

namespace App\Memory\Store;

use App\Memory\Model\MemoryEntry;
use App\Memory\Model\MemoryMetadata;
use App\Memory\Model\MemoryType;

/**
 * In-memory ring buffer for current session context.
 * Entries are ephemeral — lost on process exit unless serialized.
 */
final class ShortTermStore
{
    /** @var list<MemoryEntry> */
    private array $entries = [];

    public function __construct(
        private readonly int $maxEntries = 50,
    ) {
    }

    public function add(MemoryEntry $entry): void
    {
        $this->entries[] = $entry;

        if (\count($this->entries) > $this->maxEntries) {
            array_shift($this->entries);
        }
    }

    /**
     * Add a raw turn (user message, assistant response, tool result).
     */
    public function addTurn(string $role, string $content): MemoryEntry
    {
        $entry = new MemoryEntry(
            id: 'st-' . bin2hex(random_bytes(8)),
            content: "[{$role}] {$content}",
            type: MemoryType::SHORT_TERM,
            metadata: new MemoryMetadata(
                importance: 0.3,
                source: 'session',
            ),
        );

        $this->add($entry);

        return $entry;
    }

    public function get(string $id): ?MemoryEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return list<MemoryEntry>
     */
    public function getAll(): array
    {
        return $this->entries;
    }

    /**
     * @return list<MemoryEntry>
     */
    public function getRecent(int $count): array
    {
        return \array_slice($this->entries, -$count);
    }

    /**
     * Search entries by keyword (simple substring match).
     *
     * @return list<MemoryEntry>
     */
    public function search(string $keyword): array
    {
        $keyword = mb_strtolower($keyword);

        return array_values(array_filter(
            $this->entries,
            static fn (MemoryEntry $e) => str_contains(mb_strtolower($e->content), $keyword),
        ));
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    /**
     * Serialize to JSON for session persistence.
     *
     * @return list<array<string, mixed>>
     */
    public function serialize(): array
    {
        return array_map(
            static fn (MemoryEntry $e) => $e->jsonSerialize(),
            $this->entries,
        );
    }

    /**
     * Restore from serialized session data.
     *
     * @param list<array<string, mixed>> $data
     */
    public function restore(array $data): void
    {
        $this->entries = array_map(
            static fn (array $d) => MemoryEntry::fromArray($d),
            $data,
        );
    }
}
