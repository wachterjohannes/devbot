# DevBot

AI-powered development process agent built on Symfony/TUI and Symfony/AI.

## Quick Start (Standalone Binary)

```bash
# Download (Linux x86_64 — see Releases for other platforms)
curl -Lo devbot.tar.gz https://github.com/wachterjohannes/devbot/releases/latest/download/devbot-linux-x86_64.tar.gz
tar xzf devbot.tar.gz

# Prerequisites
ollama pull kimi-k2.5:cloud
ollama pull nomic-embed-text

# Interactive setup (creates config, directories, vector store)
./devbot setup

# Run
./devbot run
```

No PHP, Composer, or dependencies required -- everything is bundled via [FrankenPHP](https://frankenphp.dev).

## Quick Start (From Source)

```bash
# Prerequisites
ollama pull kimi-k2.5:cloud
ollama pull nomic-embed-text

# Clone & install
git clone https://github.com/wachterjohannes/devbot.git && cd devbot
composer install

# Interactive setup
php bin/devbot setup

# Run
php bin/devbot run
```

## Commands

| Command | Description |
|---------|-------------|
| `php bin/devbot setup` | Interactive setup wizard (config, dirs, vector store) |
| `php bin/devbot setup --headless` | Setup including systemd service for server deployment |
| `php bin/devbot run` | Start TUI chat interface |
| `php bin/devbot run --headless` | Headless mode: heartbeat + socket server |
| `php bin/devbot client` | Connect to headless server (local) |
| `php bin/devbot client --host user@server` | Connect to headless server (remote via SSH) |
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
| **F4** | Tool execution logs tab |
| **Arrow keys** | Navigate lists (board, memory) |
| **F6** | Cycle focus between widgets |
| **Ctrl+Q** | Quit |

## 33 Agent Tools

| Category | Tools |
|----------|-------|
| **Memory** (8) | memory_search, memory_grep, memory_read, memory_prune, memory_add, memory_remove, memory_update, memory_list |
| **Kanban** (4) | kanban_list, kanban_create_card, kanban_move_card, kanban_update_card |
| **Skills** (6) | skill_create, skill_update, skill_run, skill_list, skill_toggle, skill_delete |
| **Heartbeat** (3) | schedule_task, list_scheduled, cancel_scheduled |
| **Git** (4) | git_status, git_commit, github (issues/PRs/comments via gh CLI), gitlab (issues/MRs/comments via glab CLI) |
| **Web** (2) | web_search, web_fetch |
| **Shell** (1) | shell_exec (sandboxed with command allowlist) |
| **Claude** (1) | claude_delegate (plan/dev modes, model selection) |
| **Client** (4) | client_exec, client_file_read, client_file_list, client_claude_delegate (reverse execution on connected client) |

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
- F4 Logs: tool execution log with status, arguments, results (persisted to `var/log/tool_calls.jsonl`)

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

### Standalone Binaries
- FrankenPHP-embedded static binaries (no PHP required)
- Linux x86_64, Linux ARM64, macOS ARM64
- GitHub Actions CI/CD for automated releases
- Single binary includes PHP 8.4, all extensions, and all dependencies

### Quality
- PHPStan level 6: 0 errors
- 86 tests (unit + integration), 215 assertions
