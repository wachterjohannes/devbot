<?php

declare(strict_types=1);

namespace App\Tool\Memory;

use App\Memory\MemoryManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Permanently remove or archive a memory entry.
 */
#[AsTool('memory_remove', 'Permanently remove a memory entry by ID. Use when information is outdated or wrong.')]
final readonly class MemoryRemoveTool
{
    public function __construct(
        private MemoryManager $memoryManager,
    ) {
    }

    /**
     * @param string  $id     The memory entry ID to remove
     * @param string  $reason Optional reason for removal
     *
     * @return array{removed: bool, id: string}
     */
    public function __invoke(string $id, string $reason = ''): array
    {
        $removed = $this->memoryManager->remove($id);

        return [
            'removed' => $removed,
            'id' => $id,
        ];
    }
}
