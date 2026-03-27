<?php

declare(strict_types=1);

namespace App\Memory\Strategy;

/**
 * Heuristic importance scorer for memory entries.
 * Scores content 0.0–1.0 based on keyword patterns and structural signals.
 * Used as a fast fallback when LLM-based scoring is too expensive.
 */
final class RuleBasedImportanceScorer
{
    /** @var list<array{pattern: string, boost: float}> */
    private const BOOST_RULES = [
        ['pattern' => 'decision', 'boost' => 0.15],
        ['pattern' => 'decided', 'boost' => 0.15],
        ['pattern' => 'important', 'boost' => 0.1],
        ['pattern' => 'critical', 'boost' => 0.15],
        ['pattern' => 'bug', 'boost' => 0.1],
        ['pattern' => 'fix', 'boost' => 0.05],
        ['pattern' => 'error', 'boost' => 0.1],
        ['pattern' => 'architecture', 'boost' => 0.1],
        ['pattern' => 'pattern', 'boost' => 0.05],
        ['pattern' => 'preference', 'boost' => 0.05],
        ['pattern' => 'convention', 'boost' => 0.05],
        ['pattern' => 'never', 'boost' => 0.1],
        ['pattern' => 'always', 'boost' => 0.1],
        ['pattern' => 'remember', 'boost' => 0.15],
        ['pattern' => 'deploy', 'boost' => 0.1],
        ['pattern' => 'security', 'boost' => 0.15],
        ['pattern' => 'password', 'boost' => -0.3],
        ['pattern' => 'secret', 'boost' => -0.3],
        ['pattern' => 'api_key', 'boost' => -0.3],
    ];

    /**
     * Score a piece of content for importance.
     *
     * @return float Score between 0.0 and 1.0
     */
    public function score(string $content): float
    {
        $score = 0.3; // baseline
        $lowerContent = mb_strtolower($content);

        // Keyword boosts
        foreach (self::BOOST_RULES as $rule) {
            if (str_contains($lowerContent, $rule['pattern'])) {
                $score += $rule['boost'];
            }
        }

        // Length signal: very short = less important, medium = normal, very long = slightly more
        $length = mb_strlen($content);
        if ($length < 20) {
            $score -= 0.1;
        } elseif ($length > 500) {
            $score += 0.05;
        }

        // Structure signal: headings and lists suggest organized knowledge
        if (preg_match('/^#{1,3}\s/m', $content)) {
            $score += 0.05;
        }

        if (preg_match('/^[-*]\s/m', $content)) {
            $score += 0.03;
        }

        return max(0.0, min(1.0, $score));
    }
}
