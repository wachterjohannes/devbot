# Memory System

DevBot has a four-tier memory system that improves on single-file approaches like CLAUDE.md.

## Memory Tiers

### Short-Term (session)
- In-memory ring buffer (last 50 turns)
- Lost on process exit
- Used for immediate conversation context

### Long-Term (persistent)
- Markdown files in `memory/long-term/` organized by topic
- JSON metadata index for fast lookups
- Topics: `projects/`, `decisions/`, `patterns/`, `preferences/`
- Human-readable, git-versionable

### Episodic (event log)
- JSON files per day in `memory/episodic/{date}/`
- What happened, when, and why
- Useful for: "When did we decide X?", "What did we do last week?"

### Semantic (vector search)
- SQLite vector store (`var/devbot_memory.sqlite`)
- All persisted entries are automatically embedded and indexed
- Enables fuzzy/semantic search across all memory

## How Search Works

DevBot uses **agentic multi-hop search** — not simple single-query retrieval:

1. `memory_search` — broad semantic discovery (find entries by meaning)
2. `memory_grep` — exact keyword matching (names, dates, specific terms)
3. `memory_read` — get full content when snippets aren't enough
4. `memory_prune` — discard irrelevant results to keep focus
5. Iterate — each discovery may reveal new leads

Results are automatically deduplicated across calls and pruned entries are excluded.

## Automatic Injection

Before each agent call, the `MemoryInjectionProcessor` runs a lightweight semantic search using the user's latest message and injects the top 3 most relevant memories into context. This gives the agent baseline awareness without explicit searching.

## Storage Locations

```
memory/
├── long-term/          # Persistent facts (markdown + JSON index)
│   ├── index.json
│   ├── projects/
│   ├── decisions/
│   └── patterns/
├── episodic/           # Event log (JSON by date)
│   └── 2026-03-27/
└── short-term/         # Session dumps (future)
var/
└── devbot_memory.sqlite  # Vector store
```

## Memory Lifecycle

### Session End

When you quit DevBot (Ctrl+Q), the `SessionEndHandler` runs:

1. Scores every conversation turn with the `RuleBasedImportanceScorer`
2. Entries above the threshold (0.4) are persisted to long-term or episodic memory
3. Events ("deployed", "fixed", "completed") go to episodic; facts/decisions go to long-term
4. Topics are auto-inferred (decisions, patterns, preferences, projects)

### Garbage Collection

The `GarbageCollector` removes stale memories using time-based decay:

- **Decay**: importance drops by 0.01 per day since last access
- **Access boost**: frequently accessed entries resist decay (+0.02 per access, max +0.2)
- **Threshold**: entries below 0.2 effective importance are removed
- **Episodic**: entries older than 90 days are purged

### Context Window Management

The `ContextTruncationProcessor` keeps conversations within the 128k token budget:

- Runs as the last input processor (priority -50)
- Triggers at 80% usage — drops oldest conversation turns
- Always preserves: system prompt, identity context, memory injection
- Minimum 4 recent turns are always kept
