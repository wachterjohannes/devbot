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

# Install & setup
composer install
php bin/devbot ai:store:setup ai.store.sqlite.memory_store

# Run
php bin/devbot run
```

## Commands

| Command | Description |
|---------|-------------|
| `php bin/devbot run` | Start TUI chat interface |
| `php bin/devbot run --headless` | Heartbeat + Telegram only (future) |
| `php bin/devbot ai:store:setup ai.store.sqlite.memory_store` | Set up SQLite vector store |
| `php vendor/bin/phpunit` | Run tests |
| `php vendor/bin/phpstan analyse` | Static analysis |
| `php vendor/bin/php-cs-fixer fix` | Fix code style |

## TUI Controls

| Key | Action |
|-----|--------|
| **Ctrl+Enter** | Send message |
| **F1** | Chat tab |
| **F2** | Kanban board tab |
| **F3** | Memory browser tab |
| **Arrow keys** | Navigate lists (board, memory) |
| **F6** | Cycle focus between widgets |
| **Ctrl+Q** | Quit |

## 27 Agent Tools

| Category | Tools |
|----------|-------|
| **Memory** (8) | memory_search, memory_grep, memory_read, memory_prune, memory_add, memory_remove, memory_update, memory_list |
| **Kanban** (4) | kanban_list, kanban_create_card, kanban_move_card, kanban_update_card |
| **Skills** (6) | skill_create, skill_update, skill_run, skill_list, skill_toggle, skill_delete |
| **Heartbeat** (3) | schedule_task, list_scheduled, cancel_scheduled |
| **Git** (2) | git_status, git_commit |
| **Web** (2) | web_search, web_fetch |
| **Shell** (1) | shell_exec (sandboxed with command allowlist) |
| **Claude** (1) | claude_delegate (plan/dev modes, model selection) |

## Documentation

See [docs/](docs/README.md) for full user and developer documentation.

## Architecture

See [PLAN.md](PLAN.md) for full architecture, and [CLAUDE.md](CLAUDE.md) for development conventions.

## What's Built

### Foundation
- Symfony 8.x with symfony/ai (dev-main) and symfony/tui (fabpot PR #63778)
- TUI chat with streaming responses via AmpHttpClient (non-blocking I/O)
- Identity system (SOUL.md, IDENTITY.md, human profiles) with injection processor
- Vendor patches for NDJSON streaming and store setup in dev mode

### Memory System
- Four tiers: ShortTerm (ring buffer), LongTerm (markdown+JSON), Episodic (dated JSON), Semantic (SQLite vectors)
- Agentic multi-hop RAG via MemoryCorpus (search/grep/read/prune with dedup)
- MemoryInjectionProcessor auto-injects relevant context before each agent call
- Memory lifecycle: SessionEndHandler extracts learnings, GarbageCollector prunes stale entries
- Context window management: 128k token budget, auto-truncates at 80% usage
- Profile auto-learning: extracts preferences, tech context, role info from conversations

### Kanban + Git + Shell
- Kanban board with JSON persistence, WIP limits, 4 tools
- Git tools: status/log/diff/branch, stage+commit
- Sandboxed shell exec with command allowlist (git, composer, php, npm, make, grep, find, ls, etc.)

### Tabbed TUI
- ANSI-colored tab bar with DevBot logo
- F1 Chat: streaming with thinking token animation
- F2 Board: kanban columns with cards, priorities, labels
- F3 Memory: entry list with content viewer

### Skills + Heartbeat
- Markdown-based reusable workflows the bot creates and runs
- Heartbeat: Fiber-based background scheduler (cron, interval, one-off tasks)

### Claude Code Delegation
- `claude_delegate` tool: plan mode (read-only) and dev mode (acceptEdits)
- Model selection: sonnet (default), opus, haiku

### Vendor Patches
- `patches/ollama-ndjson-streaming.patch` — NDJSON streaming ([PR #1827](https://github.com/symfony/ai/pull/1827))
- `patches/platform-ndjson-result.patch` — NdjsonHttpResult class ([PR #1827](https://github.com/symfony/ai/pull/1827))
- `patches/ai-bundle-traceable-store-managed.patch` — TraceableStore setup/drop ([PR #1828](https://github.com/symfony/ai/pull/1828))

### Quality
- PHPStan level 6: 0 errors
- 86 tests (unit + integration), 215 assertions
