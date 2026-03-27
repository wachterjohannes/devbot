<?php

declare(strict_types=1);

namespace App\Tool\Web;

use App\Bridge\OllamaWebBridge;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Fetch a web page via Ollama's web fetch API.
 * Returns the page title and content as clean markdown.
 */
#[AsTool('web_fetch', 'Fetch a web page by URL. Returns the page title and content as markdown.')]
final readonly class WebFetchTool
{
    public function __construct(
        private OllamaWebBridge $bridge,
    ) {
    }

    /**
     * @param string $url The URL to fetch
     *
     * @return array{title: string, content: string, url: string}
     */
    public function __invoke(string $url): array
    {
        return $this->bridge->fetch($url);
    }
}
