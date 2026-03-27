<?php

declare(strict_types=1);

namespace App\Bridge;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bridge to Ollama's web search and fetch REST APIs.
 * These are separate from the Ollama LLM API — they provide internet access.
 *
 * @see https://ollama.com/api/web_search
 * @see https://ollama.com/api/web_fetch
 */
final readonly class OllamaWebBridge
{
    private const SEARCH_URL = 'https://ollama.com/api/web_search';
    private const FETCH_URL = 'https://ollama.com/api/web_fetch';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private int $searchMaxResults = 5,
        private int $fetchTimeout = 15,
    ) {
    }

    /**
     * Search the web via Ollama API.
     *
     * @return array{results: list<array{title: string, url: string, content: string}>}
     */
    public function search(string $query, int $maxResults = 0): array
    {
        if ($maxResults <= 0) {
            $maxResults = $this->searchMaxResults;
        }

        $response = $this->httpClient->request('POST', self::SEARCH_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'q' => $query,
                'max_results' => min($maxResults, 10),
            ],
            'timeout' => $this->fetchTimeout,
        ]);

        return $response->toArray();
    }

    /**
     * Fetch a web page via Ollama API. Returns clean markdown content.
     *
     * @return array{title: string, content: string, url: string}
     */
    public function fetch(string $url): array
    {
        $response = $this->httpClient->request('POST', self::FETCH_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'url' => $url,
            ],
            'timeout' => $this->fetchTimeout,
        ]);

        return $response->toArray();
    }
}
