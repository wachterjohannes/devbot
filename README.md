# DevBot

AI-powered development process agent built on Symfony/TUI and Symfony/AI.

## Quick Start

```bash
# Prerequisites
ollama pull kimi-k2.5:cloud
ollama pull nomic-embed-text

# Configure
cp .env .env.local
# Edit .env.local with your OLLAMA_API_KEY and paths

# Run
php bin/devbot run
```

## Commands

| Command | Description |
|---------|-------------|
| `php bin/devbot run` | Start TUI chat interface |
| `php bin/devbot run --headless` | Heartbeat + Telegram only (future) |
| `php vendor/bin/phpunit` | Run tests |
| `php vendor/bin/phpstan analyse` | Static analysis |
| `php vendor/bin/php-cs-fixer fix` | Fix code style |

## TUI Controls

- **Ctrl+Enter** — Send message
- **Ctrl+Q** — Quit

## Architecture

See [PLAN.md](PLAN.md) for full architecture, and [CLAUDE.md](CLAUDE.md) for development conventions.

## Current State: Phase 6

### Phase 1 — Foundation
- Symfony 8.x with symfony/ai (dev-main) and symfony/tui (fabpot PR #63778)
- TUI chat with streaming responses (TextWidget + EditorWidget with border)
- Agent wired to Ollama/kimi-k2.5:cloud via AmpHttpClient (non-blocking I/O)
- Identity system (SOUL.md, IDENTITY.md, human profiles) with injection processor
- Web search/fetch tools via Ollama REST API
- Vendor patches for NDJSON streaming (symfony/ai PR #1827)

### Phase 2 — Memory System
- Four memory tiers: ShortTerm (ring buffer), LongTerm (markdown+JSON), Episodic (dated JSON), Semantic (SQLite vectors)
- MemoryManager facade routing across all stores with auto-indexing
- Agentic multi-hop RAG via MemoryCorpus (search/grep/read/prune with dedup)
- 8 agent tools: memory_search, memory_grep, memory_read, memory_prune, memory_add, memory_remove, memory_update, memory_list
- MemoryInjectionProcessor auto-injects relevant context before each agent call
- RuleBasedImportanceScorer for heuristic memory scoring

### Phase 3 — Kanban + Git
- Kanban board with JSON persistence (Board, Column, Card models with WIP limits)
- KanbanManager for CRUD with automatic save
- 4 kanban tools: kanban_list, kanban_create_card, kanban_move_card, kanban_update_card
- 2 git tools: git_status (status/log/diff/branch), git_commit (stage + commit)
### Phase 5 — Skills + Heartbeat
- Skill system: markdown-based reusable workflows the bot creates and runs
- SkillParser/SkillManager for CRUD with index.json registry
- SkillRunner executes skills by building prompts and calling the agent
- 6 skill tools: skill_create, skill_update, skill_run, skill_list, skill_toggle, skill_delete
- HeartbeatLoop: Fiber-based background scheduler (cron, interval, one-off)
- 3 scheduled task tools: schedule_task, list_scheduled, cancel_scheduled
### Phase 6 — Claude Code Delegation
- `claude_delegate` tool: delegates tasks to Claude Code (`claude -p` subprocess)
- Plan mode (`--permission-mode plan`): read-only analysis, architecture, code review
- Dev mode (`--permission-mode acceptEdits`): coding, refactoring, debugging
- Model selection: sonnet (default), opus (complex), haiku (quick)
- Claude Code platform registered manually (no bundle config yet)
- 26 total agent tools registered

### Vendor Patches
- `patches/ollama-ndjson-streaming.patch` — OllamaClient uses NdjsonHttpResult ([PR #1827](https://github.com/symfony/ai/pull/1827))
- `patches/platform-ndjson-result.patch` — adds NdjsonHttpResult class for NDJSON streaming ([PR #1827](https://github.com/symfony/ai/pull/1827))
- `patches/ai-bundle-traceable-store-managed.patch` — TraceableStore implements ManagedStoreInterface ([PR #1828](https://github.com/symfony/ai/pull/1828))

### Quality
- PHPStan level 6: 0 errors
- 72 tests (unit + integration) passing
