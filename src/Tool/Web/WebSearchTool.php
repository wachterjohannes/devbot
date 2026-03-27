<?php

declare(strict_types=1);

namespace App\Tool\Web;

use App\Bridge\OllamaWebBridge;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Search the web via Ollama's web search API.
 * Returns titles, URLs, and content snippets for top results.
 */
#[AsTool('web_search', 'Search the web for information. Returns titles, URLs, and content snippets.')]
final readonly class WebSearchTool
{
    public function __construct(
        private OllamaWebBridge $bridge,
    ) {
    }

    /**
     * @param string $query      The search query
     * @param int    $maxResults Maximum number of results (1-10, default 5)
     *
     * @return array<int, array{title: string, url: string, content: string}>
     */
    public function __invoke(string $query, int $maxResults = 5): array
    {
        $result = $this->bridge->search($query, $maxResults);

        return $result['results'];
    }
}
