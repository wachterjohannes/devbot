<?php

declare(strict_types=1);

namespace App\Identity\Updater;

use App\Memory\Store\ShortTermStore;

/**
 * Observes conversations and extracts profile updates.
 * Analyzes session turns for: preferences, corrections, tech context, communication style.
 *
 * Uses heuristic pattern matching (no LLM call needed).
 * Run at session end to propose updates to human profiles.
 */
final readonly class ProfileLearner
{
    /** @var list<array{pattern: string, section: string, extractor: string}> */
    private const PATTERNS = [
        ['pattern' => 'i prefer', 'section' => 'Preferences', 'extractor' => 'preference'],
        ['pattern' => 'i like', 'section' => 'Preferences', 'extractor' => 'preference'],
        ['pattern' => 'i don\'t like', 'section' => 'Preferences', 'extractor' => 'preference'],
        ['pattern' => 'i hate', 'section' => 'Preferences', 'extractor' => 'preference'],
        ['pattern' => 'always use', 'section' => 'Preferences', 'extractor' => 'preference'],
        ['pattern' => 'never use', 'section' => 'Preferences', 'extractor' => 'preference'],
        ['pattern' => 'i work on', 'section' => 'Tech Context', 'extractor' => 'context'],
        ['pattern' => 'i\'m working on', 'section' => 'Tech Context', 'extractor' => 'context'],
        ['pattern' => 'we use', 'section' => 'Tech Context', 'extractor' => 'context'],
        ['pattern' => 'our stack', 'section' => 'Tech Context', 'extractor' => 'context'],
        ['pattern' => 'my role', 'section' => 'Basics', 'extractor' => 'basic'],
        ['pattern' => 'i am a', 'section' => 'Basics', 'extractor' => 'basic'],
        ['pattern' => 'i\'m a', 'section' => 'Basics', 'extractor' => 'basic'],
        ['pattern' => 'call me', 'section' => 'Basics', 'extractor' => 'basic'],
    ];

    public function __construct(
        private ShortTermStore $shortTermStore,
    ) {
    }

    /**
     * Analyze the current session and return proposed profile updates.
     *
     * @return list<array{section: string, insight: string, source: string}>
     */
    public function extractInsights(): array
    {
        $insights = [];

        foreach ($this->shortTermStore->getAll() as $entry) {
            // Only analyze user messages
            if (!str_starts_with($entry->content, '[user]')) {
                continue;
            }

            $content = substr($entry->content, 7); // Strip "[user] "
            $lower = mb_strtolower($content);

            foreach (self::PATTERNS as $pattern) {
                if (str_contains($lower, $pattern['pattern'])) {
                    $insights[] = [
                        'section' => $pattern['section'],
                        'insight' => trim($content),
                        'source' => $entry->id,
                    ];
                    break; // One match per message
                }
            }
        }

        return $this->deduplicateInsights($insights);
    }

    /**
     * Format insights as a markdown patch for a human profile.
     *
     * @param list<array{section: string, insight: string, source: string}> $insights
     */
    public function formatAsProfilePatch(string $humanName, array $insights): string
    {
        if ($insights === []) {
            return '';
        }

        $sections = [];
        foreach ($insights as $insight) {
            $sections[$insight['section']][] = $insight['insight'];
        }

        $patch = "# Proposed updates for {$humanName}\n\n";

        foreach ($sections as $section => $items) {
            $patch .= "## {$section}\n";
            foreach ($items as $item) {
                $patch .= "- {$item}\n";
            }
            $patch .= "\n";
        }

        return $patch;
    }

    /**
     * @param list<array{section: string, insight: string, source: string}> $insights
     * @return list<array{section: string, insight: string, source: string}>
     */
    private function deduplicateInsights(array $insights): array
    {
        $seen = [];
        $unique = [];

        foreach ($insights as $insight) {
            $key = $insight['section'] . '|' . mb_strtolower($insight['insight']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $insight;
            }
        }

        return $unique;
    }
}
