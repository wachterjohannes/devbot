<?php

declare(strict_types=1);

namespace App\Tool\Memory;

use App\Memory\Search\MemoryCorpus;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Keyword search across long-term memory content.
 * Use for specific facts: names, dates, exact terms.
 * Does NOT add to working set — use memory_read for that.
 */
#[AsTool('memory_grep', 'Keyword search across memory content. Use for specific facts, names, dates, exact terms.')]
final readonly class MemoryGrepTool
{
    public function __construct(
        private MemoryCorpus $corpus,
    ) {
    }

    /**
     * @param string $pattern Keyword or phrase to search for (case-insensitive)
     *
     * @return array<int, array{id: string, line: string, lineNumber: int}>
     */
    public function __invoke(string $pattern): array
    {
        return $this->corpus->grep($pattern);
    }
}
