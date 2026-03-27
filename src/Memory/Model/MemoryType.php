<?php

declare(strict_types=1);

namespace App\Memory\Model;

/**
 * The four memory tiers in DevBot's memory system.
 */
enum MemoryType: string
{
    /** Current session context, ephemeral ring buffer. */
    case SHORT_TERM = 'short_term';

    /** Persistent facts: projects, decisions, patterns, preferences. */
    case LONG_TERM = 'long_term';

    /** Chronological event log: task completions, decisions, interactions. */
    case EPISODIC = 'episodic';

    /** Vector-indexed chunks for semantic/fuzzy search. */
    case SEMANTIC = 'semantic';
}
