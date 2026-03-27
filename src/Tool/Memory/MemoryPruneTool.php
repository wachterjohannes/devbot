<?php

declare(strict_types=1);

namespace App\Tool\Memory;

use App\Memory\Search\MemoryCorpus;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Permanently exclude an irrelevant memory entry from future search results this session.
 * Keeps the working set focused by discarding noise.
 */
#[AsTool('memory_prune', 'Exclude an irrelevant memory entry from future search results. Keeps working set focused.')]
final readonly class MemoryPruneTool
{
    public function __construct(
        private MemoryCorpus $corpus,
    ) {
    }

    /**
     * @param string $id The memory entry ID to prune
     *
     * @return array{pruned: string, working_set: string}
     */
    public function __invoke(string $id): array
    {
        $this->corpus->prune($id);

        return [
            'pruned' => $id,
            'working_set' => $this->corpus->getWorkingSetSummary(),
        ];
    }
}
