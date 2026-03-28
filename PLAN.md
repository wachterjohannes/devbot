# DevBot — AI-Powered Development Process Agent

## Project Vision

DevBot is a self-hosted, terminal-based AI development agent built on **Symfony/TUI** and **Symfony/AI**. It manages development workflows end-to-end: planning tasks on a kanban board, communicating with GitHub/GitLab, interacting via Telegram, and using **Ollama** (with Kimi K2 as default model) for reasoning. For complex coding tasks, it delegates to **Claude Code** (spawned as a subprocess via `claude -p`). It runs on a local machine or V-Server with a rich terminal UI.

Its key differentiator is a **persistent, structured memory system** — inspired by but improving upon Claude Code's CLAUDE.md/auto-memory pattern — combined with **identity files** (HUMAN, SOUL, IDENTITY) that give the bot a stable personality and awareness of the people it works with.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4+ |
| Framework | Symfony 8.x |
| Terminal UI | `symfony/tui` — **use directly from fabpot's `tui` branch** ([PR #63778](https://github.com/symfony/symfony/pull/63778)) |
| AI Backbone | `symfony/ai-agent`, `symfony/ai-platform`, `symfony/ai-chat` |
| AI Provider (main) | **Ollama** via `symfony/ai-ollama-platform` — default model: `kimi-k2` (configurable) |
| AI Provider (embeddings) | **Ollama** with local embedding model (e.g. `nomic-embed-text`, `mxbai-embed-large`) |
| Coding Agent Bridge | `symfony/ai-claude-code-platform` — official Platform bridge, spawns `claude -p` subprocess |
| MCP Integration | `symfony/mcp-bundle` (official `mcp/sdk` v0.4+) |
| MCP Server | `symfony/ai-mate` patterns / custom `#[McpTool]` attributes |
| Vector Store | `symfony/ai-sqlite-store` (zero-dependency, single-file) |
| Database | SQLite for everything: kanban state, memory metadata, vector store |
| Telegram | `php-telegram-bot/core` or `telegram-bot/api` |
| Git Integration | GitHub API via `knplabs/github-api`, GitLab API via `m4tthumphrey/php-gitlab-api` |
| Event Loop | `revolt/event-loop` (shared with TUI) |
| Async HTTP | `amphp/http-client` (Revolt-compatible) |

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        DevBot Process                           │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                   Symfony/TUI Shell                      │   │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌───────────┐   │   │
│  │  │  Chat    │ │  Kanban  │ │  Memory  │ │  Logs/    │   │   │
│  │  │  Panel   │ │  Board   │ │  Browser │ │  Status   │   │   │
│  │  └──────────┘ └──────────┘ └──────────┘ └───────────┘   │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                   │
│  ┌───────────────────────────┴──────────────────────────────┐   │
│  │                     Agent Core                            │   │
│  │                                                           │   │
│  │  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐   │   │
│  │  │ Reasoning   │  │ Memory       │  │ Identity       │   │
│  │  │ Engine      │  │ Manager      │  │ System         │   │
│  │  │ (Ollama/    │  │              │  │ (SOUL/HUMAN/   │   │
│  │  │  Kimi K2)   │  │ - Short-term │  │  IDENTITY)     │   │
│  │  │             │  │ - Long-term  │  │                │   │
│  │  │             │  │ - Episodic   │  │                │   │
│  │  │             │  │ - Semantic   │  │                │   │
│  │  └─────────────┘  └──────────────┘  └────────────────┘   │
│  └───────────────────────────────────────────────────────────┘   │
│                              │                                   │
│  ┌───────────────────────────┴──────────────────────────────┐   │
│  │                   Tool / MCP Layer                        │   │
│  │                                                           │   │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌───────┐  │   │
│  │  │GitHub/ │ │Kanban  │ │Telegram│ │File    │ │Shell  │  │   │
│  │  │GitLab  │ │Board   │ │Bridge  │ │System  │ │Exec   │  │   │
│  │  │MCP     │ │Tool    │ │Tool    │ │Tool    │ │Tool   │  │   │
│  │  └────────┘ └────────┘ └────────┘ └────────┘ └───────┘  │   │
│  │  ┌────────┐ ┌──────────────────┐ ┌──────────────────┐    │   │
│  │  │Memory  │ │Claude Code       │ │External MCP      │    │   │
│  │  │Tools   │ │Subagent (via     │ │Servers           │    │   │
│  │  │        │ │ai-claude-code-   │ │(ai-mate, etc.)   │    │   │
│  │  │        │ │platform bridge)  │ │                  │    │   │
│  │  └────────┘ └──────────────────┘ └──────────────────┘    │   │
│  └───────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Directory Structure

```
devbot/
├── CLAUDE.md                          # Claude Code instructions for developing DevBot
├── PLAN.md                            # This file
├── README.md
├── ARCHITECTURE.md
│
├── bin/
│   └── devbot                         # Entry point (symfony console app)
│
├── config/
│   ├── packages/
│   │   ├── ai.yaml                    # symfony/ai agent + platform config
│   │   ├── mcp.yaml                   # MCP client + server config
│   │   └── devbot.yaml                # DevBot-specific settings
│   ├── services.yaml
│   └── routes.yaml
│
├── identity/                          # Bot Identity System (versioned)
│   ├── SOUL.md                        # Bot personality, values, communication style
│   ├── IDENTITY.md                    # Bot self-knowledge, capabilities, constraints
│   └── humans/                        # Per-person profiles
│       ├── _template.md               # Template for new human profiles
│       ├── johannes.md                # Example: your profile
│       └── ...
│
├── memory/                            # Persistent Memory System
│   ├── index.json                     # Memory index with metadata + routing
│   ├── short-term/                    # Current session context (ephemeral)
│   │   └── session-{id}.json
│   ├── long-term/                     # Consolidated facts, decisions, patterns
│   │   ├── projects/                  # Per-project memory
│   │   │   └── {project-slug}.md
│   │   ├── decisions/                 # Architectural decisions log
│   │   │   └── {date}-{slug}.md
│   │   ├── patterns/                  # Recurring patterns + lessons learned
│   │   │   └── {category}.md
│   │   └── preferences/               # User/team preferences
│   │       └── {topic}.md
│   ├── episodic/                      # Event/interaction logs
│   │   └── {date}/
│   │       └── {timestamp}-{type}.json
│   └── semantic/                      # Vector-indexed chunks for RAG
│       └── (managed by symfony/ai-store)
│
├── kanban/                            # Kanban board persistence
│   ├── board.json                     # Board state (columns, WIP limits)
│   └── archive/                       # Completed/archived cards
│
├── skills/                            # Bot-managed skill definitions
│   ├── index.json                     # Skill registry (id, trigger, enabled, last_run)
│   └── archive/                       # Disabled/deleted skills
│
├── heartbeat/                         # Heartbeat system state
│   └── scheduled.json                 # One-off scheduled tasks
│
├── src/
│   ├── Kernel.php
│   │
│   ├── Command/
│   │   ├── DevBotCommand.php          # Main TUI entry: `bin/devbot run`
│   │   ├── MemoryCommand.php          # Memory CLI: `bin/devbot memory:search|add|gc`
│   │   └── SetupCommand.php           # First-run setup wizard
│   │
│   ├── Tui/                           # TUI Widgets + Layouts
│   │   ├── App.php                    # Root TUI application (layout, tabs, keybindings)
│   │   ├── Widget/
│   │   │   ├── ChatWidget.php         # Main chat interaction panel
│   │   │   ├── KanbanWidget.php       # Kanban board visualization
│   │   │   ├── MemoryBrowserWidget.php # Memory search/browse panel
│   │   │   ├── StatusBarWidget.php    # Bottom status bar (model, tokens, memory stats)
│   │   │   └── LogWidget.php          # Tool execution log stream
│   │   └── Template/                  # Twig-based TUI templates
│   │       ├── app.tui.twig
│   │       ├── chat.tui.twig
│   │       ├── kanban.tui.twig
│   │       └── memory.tui.twig
│   │
│   ├── Agent/                         # Agent Core
│   │   ├── DevBotAgent.php            # Main agent orchestrator
│   │   ├── Processor/
│   │   │   ├── MemoryInjectionProcessor.php   # Injects relevant memory into context
│   │   │   ├── IdentityInjectionProcessor.php # Injects SOUL + IDENTITY + HUMAN context
│   │   │   ├── KanbanContextProcessor.php     # Injects current task/board state
│   │   │   └── ToolResultProcessor.php        # Post-processes tool results
│   │   └── Prompt/
│   │       ├── SystemPromptBuilder.php         # Assembles system prompt from parts
│   │       └── ContextWindowManager.php        # Manages context budget allocation
│   │
│   ├── Memory/                        # Memory System
│   │   ├── MemoryManager.php          # Facade: add, search, consolidate, gc
│   │   ├── Model/
│   │   │   ├── MemoryEntry.php        # Single memory entry (content, metadata, embedding)
│   │   │   ├── MemoryType.php         # Enum: SHORT_TERM, LONG_TERM, EPISODIC, SEMANTIC
│   │   │   └── MemoryMetadata.php     # Timestamps, importance, access count, tags, source
│   │   ├── Store/
│   │   │   ├── ShortTermStore.php     # In-memory session store (ring buffer)
│   │   │   ├── LongTermStore.php      # File-based markdown store with index
│   │   │   ├── EpisodicStore.php      # Chronological event log
│   │   │   └── SemanticStore.php      # Vector store adapter (symfony/ai-store)
│   │   ├── Strategy/
│   │   │   ├── ImportanceScorerInterface.php
│   │   │   ├── LlmImportanceScorer.php     # Uses cheap model to score importance
│   │   │   ├── RuleBasedImportanceScorer.php # Heuristic fallback (keywords, repetition)
│   │   │   ├── ConsolidationStrategy.php   # Merges/summarizes related memories
│   │   │   └── DecayStrategy.php           # Time-based relevance decay
│   │   ├── Search/
│   │   │   ├── MemoryCorpus.php            # Core: wraps store + vectorizer + original docs
│   │   │   │                                #   - semanticSearch() with dedup + prune
│   │   │   │                                #   - grepDocuments() with line-level matching
│   │   │   │                                #   - found set tracking + summary formatting
│   │   │   ├── MemoryCorpusFactory.php     # Builds MemoryCorpus from all memory tiers
│   │   │   └── ContextRelevanceRanker.php  # Re-ranks results for current context
│   │   └── Lifecycle/
│   │       ├── SessionStartHandler.php     # Loads relevant memory on session start
│   │       ├── SessionEndHandler.php       # Extracts + consolidates session learnings
│   │       ├── ConsolidationJob.php        # Periodic memory compaction (cron/messenger)
│   │       └── GarbageCollector.php        # Removes stale/low-importance memories
│   │
│   ├── Identity/                      # Identity System
│   │   ├── IdentityManager.php        # Loads + manages SOUL, IDENTITY, HUMAN files
│   │   ├── HumanProfileManager.php    # CRUD for human profiles
│   │   ├── Model/
│   │   │   ├── Soul.php               # Parsed SOUL.md representation
│   │   │   ├── Identity.php           # Parsed IDENTITY.md representation
│   │   │   └── HumanProfile.php       # Parsed human profile
│   │   └── Updater/
│   │       └── ProfileLearner.php     # Auto-updates human profiles from interactions
│   │
│   ├── Kanban/                        # Kanban Board
│   │   ├── KanbanManager.php          # Board state management
│   │   ├── Model/
│   │   │   ├── Board.php
│   │   │   ├── Column.php             # Columns with WIP limits
│   │   │   ├── Card.php               # Task cards with labels, assignee, links
│   │   │   └── CardStatus.php         # Enum: BACKLOG, TODO, IN_PROGRESS, REVIEW, DONE
│   │   └── Sync/
│   │       ├── GitHubIssueSyncer.php  # Two-way sync cards <-> GitHub issues
│   │       └── GitLabIssueSyncer.php  # Two-way sync cards <-> GitLab issues
│   │
│   ├── Skill/                         # Skill System
│   │   ├── SkillManager.php           # CRUD for skill definitions
│   │   ├── SkillRunner.php            # Executes a skill by building prompt + calling agent
│   │   ├── SkillParser.php            # Parses skill markdown into structured model
│   │   └── Model/
│   │       ├── Skill.php              # Skill definition model
│   │       └── SkillTrigger.php       # Enum/value object: MANUAL, CRON, EVENT
│   │
│   ├── Heartbeat/                     # Heartbeat System
│   │   ├── HeartbeatLoop.php          # Main Fiber-based tick loop
│   │   ├── TaskScheduler.php          # Checks which tasks/skills are due
│   │   ├── TaskExecutor.php           # Runs a task via the agent
│   │   ├── ScheduledTaskManager.php   # CRUD for one-off scheduled tasks
│   │   └── Model/
│   │       └── ScheduledTask.php      # One-off task model (reminder, research, etc.)
│   │
│   ├── Tool/                          # MCP Tools (exposed to the agent)
│   │   ├── Memory/
│   │   │   ├── AgenticSearchTools.php     # Agentic search: search, grep, read, prune (PR #1825 pattern)
│   │   │   ├── MemoryCorpus.php           # Wraps vectorizer + store + dedup + prune tracking
│   │   │   ├── MemoryAddTool.php          # Add a memory entry
│   │   │   ├── MemoryRemoveTool.php       # Remove/archive a memory entry
│   │   │   ├── MemoryUpdateTool.php       # Update existing memory
│   │   │   └── MemoryListTool.php         # List memories by type/tag/date
│   │   ├── Kanban/
│   │   │   ├── KanbanListTool.php         # List board/column state
│   │   │   ├── KanbanCreateCardTool.php   # Create a new card
│   │   │   ├── KanbanMoveCardTool.php     # Move card between columns
│   │   │   └── KanbanUpdateCardTool.php   # Update card details
│   │   ├── Git/
│   │   │   ├── GitHubTool.php             # GitHub API operations (issues, PRs, repos)
│   │   │   ├── GitLabTool.php             # GitLab API operations
│   │   │   ├── GitStatusTool.php          # Local git status/log/diff
│   │   │   └── GitCommitTool.php          # Stage + commit with message
│   │   ├── Telegram/
│   │   │   ├── TelegramSendTool.php       # Send message to configured chat
│   │   │   ├── TelegramPollTool.php       # Check for incoming messages
│   │   │   └── TelegramNotifyTool.php     # Send notification (task done, review needed)
│   │   │
│   │   │   # NOTE: FileSystem tools provided by symfony/ai-filesystem-tool (no custom code)
│   │   │   # NOTE: Clock tool provided by symfony/ai-clock-tool
│   │   │
│   │   ├── Web/
│   │   │   ├── WebSearchTool.php          # Ollama web search API (ollama.com/api/web_search)
│   │   │   └── WebFetchTool.php           # Ollama web fetch API (ollama.com/api/web_fetch)
│   │   ├── Shell/
│   │   │   ├── ShellExecTool.php          # Run commands (sandboxed)
│   │   │   └── ComposerTool.php           # Composer-specific operations
│   │   ├── CodingAgent/
│   │   │   └── CodingDelegateTool.php     # Delegates coding tasks to claude_code subagent
│   │   ├── Skill/
│   │   │   ├── SkillCreateTool.php        # Create a new skill from description
│   │   │   ├── SkillUpdateTool.php        # Update existing skill definition
│   │   │   ├── SkillRunTool.php           # Execute a skill immediately
│   │   │   ├── SkillListTool.php          # List all skills with status
│   │   │   ├── SkillToggleTool.php        # Enable/disable a skill
│   │   │   └── SkillDeleteTool.php        # Delete/archive a skill
│   │   ├── Heartbeat/
│   │   │   ├── ScheduleTaskTool.php       # Schedule a one-off task (reminder, research)
│   │   │   ├── ListScheduledTool.php      # List upcoming scheduled tasks
│   │   │   └── CancelScheduledTool.php    # Cancel a scheduled task
│   │   ├── Identity/
│   │   │   ├── HumanProfileReadTool.php   # Read a human profile
│   │   │   └── HumanProfileUpdateTool.php # Update a human profile
│   │   └── Meta/
│   │       ├── SelfReflectTool.php        # Bot can reflect on its own state
│   │       └── PlanTool.php               # Create/update execution plans
│   │
│   ├── Bridge/                        # External Service Bridges
│   │   ├── Telegram/
│   │   │   ├── TelegramBotService.php     # Bot API wrapper
│   │   │   └── TelegramListener.php       # Incoming message handler (Fiber-based polling)
│   │   ├── GitHub/
│   │   │   └── GitHubApiService.php
│   │   └── GitLab/
│   │       └── GitLabApiService.php
│   │
│   └── EventListener/
│       ├── ToolExecutionListener.php      # Logs tool calls to TUI
│       ├── MemoryAutoSaveListener.php     # Auto-saves important interactions
│       └── TelegramBridgeListener.php     # Bridges Telegram messages into agent loop
│
├── templates/                         # Twig templates for TUI
│   └── tui/
│       └── ...
│
├── tests/
│   ├── Unit/
│   │   ├── Memory/
│   │   ├── Kanban/
│   │   └── Identity/
│   └── Integration/
│       ├── Agent/
│       └── Tool/
│
├── var/
│   ├── cache/
│   ├── log/
│   └── devbot_memory.sqlite             # SQLite vector store + memory metadata
│
├── composer.json
├── docker-compose.yaml                # Optional: Ollama (if not running natively)
└── Makefile                           # Common dev tasks
```

---

## Core Systems Design

### 1. Memory System (The Heart of DevBot)

The memory system is the single most important subsystem. It improves on Claude Code's CLAUDE.md pattern in several critical ways:

#### 1.1 Four Memory Tiers

**Short-Term Memory (session-scoped)**
- Ring buffer of the last N conversation turns + tool results
- Stored in-memory, serialized to `memory/short-term/session-{id}.json` on pause/exit
- Used for: immediate conversation context, working memory
- Max size: configurable (e.g., 50 turns or ~30k tokens)

**Long-Term Memory (persistent facts)**
- Structured markdown files organized by topic (projects, decisions, patterns, preferences)
- Each entry has metadata: `importance` (0.0-1.0), `created_at`, `last_accessed`, `access_count`, `tags[]`, `source` (session ID or manual)
- Index file (`memory/index.json`) maps tags/topics to file locations for fast lookup
- Used for: project knowledge, architectural decisions, coding patterns, user preferences

**Episodic Memory (event log)**
- Chronological log of significant events: task completions, decisions made, errors encountered, interactions with humans
- JSON files per day: `memory/episodic/{date}/{timestamp}-{type}.json`
- Used for: "What did we do last Tuesday?", "When did we decide to use SQLite?", learning from past mistakes

**Semantic Memory (vector-indexed)**
- Chunks of all other memory tiers, embedded and stored in a vector database via `symfony/ai-sqlite-store`
- Embeddings generated locally via Ollama embedding model (e.g. `nomic-embed-text`)
- Used for: fuzzy/semantic search ("find memories related to database migration patterns")
- Zero external dependencies — all in one SQLite file

#### 1.2 Memory Lifecycle

```
User Interaction / Tool Result
         │
         ▼
┌─────────────────────┐
│  Short-Term Store   │  ← every turn goes here
│  (ring buffer)      │
└────────┬────────────┘
         │ on session end / periodically
         ▼
┌─────────────────────┐
│  Importance Scorer  │  ← LLM-based or heuristic
│  (is this worth     │
│   remembering?)     │
└────────┬────────────┘
         │ score > threshold
         ▼
┌─────────────────────────────────────────┐
│  Router: which store(s)?                │
│                                          │
│  fact/decision/pattern → Long-Term       │
│  event/milestone → Episodic              │
│  all persisted items → Semantic (embed)  │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────────┐
│  Consolidation      │  ← periodic (cron or messenger)
│  - merge duplicates │
│  - summarize chains │
│  - decay old items  │
│  - enforce limits   │
└─────────────────────┘
```

#### 1.3 Memory Search (Agentic Multi-Hop RAG)

Instead of simple single-hop similarity search, the memory system uses the **agentic search pattern** from [symfony/ai PR #1825](https://github.com/symfony/ai/pull/1825). The agent iteratively explores the memory corpus using four specialized tools, inspired by Chroma's Context 1 model:

| Tool | Description |
|---|---|
| `memory_search` | Semantic vector search across the memory store. Returns snippets ranked by relevance. Already-seen and pruned results are automatically excluded (dedup across calls). Does NOT add to working set. |
| `memory_grep` | Line-by-line keyword matching on full memory content. Use for specific facts: names, dates, exact terms. Does NOT add to working set. |
| `memory_read` | Read full content of a memory entry by ID and add it to the working set. Use after search/grep to investigate deeper. |
| `memory_prune` | Permanently exclude an irrelevant entry from future results in the current session. Keeps the working set focused. |

The agent's search strategy (injected via system prompt):

1. **Plan** what information is needed for the current task
2. **`memory_search`** for broad semantic discovery (find entries by topic/meaning)
3. **`memory_grep`** for specific facts (names, decisions, dates, exact terms)
4. **`memory_read`** to get full content when snippets aren't enough
5. **`memory_prune`** to discard irrelevant entries and keep working set focused
6. **Iterate** — each discovery may reveal new leads requiring additional searches

This is backed by a `MemoryCorpus` class (analogous to `DocumentCorpus` in the PR) that wraps the `Vectorizer`, the SQLite store, and the original memory entries. It handles deduplication, prune tracking, and a "found summary" the agent can use to see its current working set.

The key advantage over single-hop RAG: the agent can start broad ("what do I know about database migrations?"), discover a relevant project, then narrow down ("grep for 'PostgreSQL' in that project's memories"), read the full entry, and prune irrelevant results — all in one multi-turn tool-calling loop.

Additionally, the `MemoryInjectionProcessor` still injects a small set of highly-relevant memories automatically before each agent call (using a lightweight vector search), so the agent has baseline context even without explicitly searching.

#### 1.4 Memory Tools (Agent-Accessible)

The memory tools are split into two groups:

**Agentic Search Tools** (for querying — based on PR #1825 pattern):

| Tool | Description |
|---|---|
| `memory_search` | Semantic search. Params: `query`, `limit?`. Deduped, excludes pruned. |
| `memory_grep` | Keyword search across all memory content. Params: `pattern`. Line-by-line matching. |
| `memory_read` | Read full entry by ID, add to working set. Params: `id`. |
| `memory_prune` | Exclude entry from current session results. Params: `id`. |

**Memory Management Tools** (for CRUD):

| Tool | Description |
|---|---|
| `memory_add` | Add an entry. Params: `content`, `type`, `tags[]`, `importance?` |
| `memory_remove` | Permanently remove/archive by ID. Params: `id`, `reason?` |
| `memory_update` | Update content/metadata. Params: `id`, `content?`, `tags?[]`, `importance?` |
| `memory_list` | List entries. Params: `type?`, `tags?[]`, `sort_by?`, `limit?` |

#### 1.5 Improvements Over Claude Code's Memory

| Claude Code Pattern | DevBot Improvement |
|---|---|
| Single CLAUDE.md file (200-line cap) | Multi-tier storage with no hard cap; routing to appropriate store |
| Manual maintenance required | Automatic extraction, scoring, consolidation |
| No search — full file loaded every time | **Agentic multi-hop search** (PR #1825 pattern): semantic search + grep + read + prune in iterative tool-calling loop |
| No importance scoring | LLM-based importance scoring with decay over time |
| No episodic memory | Full event log with temporal queries |
| Text-only, no embeddings | Vector-indexed semantic store via SQLite for fuzzy retrieval |
| No garbage collection | Automatic GC with configurable retention policies |
| Auto-memory is opaque | Memory browser widget in TUI for transparency |

---

### 2. Identity System

#### 2.1 SOUL.md — Bot Personality

Defines how the bot communicates and what it values. Loaded into system prompt on every interaction.

```markdown
# SOUL

## Personality
- Direct and technical; avoids fluff
- Uses code examples over long explanations
- Acknowledges uncertainty honestly
- Has a dry sense of humor in informal contexts

## Values
- Code quality over speed
- Explicit over implicit
- Small, reviewable commits
- Test coverage for critical paths

## Communication Style
- Default language: English (switches to German when the human prefers it)
- Uses markdown formatting in responses
- Prefixes status updates with emoji: ✅ done, 🔄 in progress, ❌ blocked, 💡 suggestion
- Asks clarifying questions before making big changes

## Boundaries
- Always asks for confirmation before: destructive git operations, deploying, deleting files
- Never stores secrets in memory (API keys, passwords, tokens)
- Escalates to human via Telegram when confidence < 60% on a decision
```

#### 2.2 IDENTITY.md — Self-Knowledge

What the bot knows about itself. Updated as capabilities change.

```markdown
# IDENTITY

## What I Am
- DevBot: an AI development process agent
- Running on: [machine name / IP]
- Primary model: Kimi K2 via Ollama (local)
- Coding delegation: Claude Code via symfony/ai-claude-code-platform
- Embeddings: nomic-embed-text via Ollama
- Symfony/AI version: 0.6.x

## My Capabilities
- Plan and manage tasks on a kanban board
- Interact with GitHub/GitLab (issues, PRs, reviews)
- Read, write, and search files on the local system
- Execute shell commands (sandboxed)
- Send/receive messages via Telegram
- Search and manage my own persistent memory
- Connect to external MCP servers for additional tools

## My Limitations
- I cannot browse the web (unless an MCP server provides it)
- I do not have direct database access (use tools)
- My memory is only as good as my consolidation — I may forget low-importance details
- I run single-threaded (Fibers give concurrency, not parallelism)

## Current Projects
- (auto-populated from kanban board + memory)
```

#### 2.3 HUMAN Files — Person Profiles

Each person the bot interacts with gets a profile in `identity/humans/`. The bot auto-learns from interactions but humans can also edit these directly.

```markdown
# Human: Johannes Wachter

## Basics
- Role: Developer / Entrepreneur
- Location: Dornbirn, Austria
- Languages: German (native), English (fluent)
- Preferred language for communication: German for casual, English for code/technical

## Working Style
- Prefers concise, direct communication
- Likes to see the plan before execution starts
- Reviews PRs carefully — prefers small, focused commits
- Active on Telegram for async communication

## Tech Context
- Deep Symfony/PHP expertise
- Maintains Sulu CMS, ai-mate, sulu.ai
- Familiar with: Docker, Nomad, Hetzner, GitLab CI, PostgreSQL
- Currently working on: Akademie Management Software, symfony/ai ecosystem

## Preferences
- Testing: PHPUnit, prefers unit tests with clear assertions
- Code style: PHP-CS-Fixer, PSR-12
- Git: conventional commits, feature branches
- Documentation: markdown, prefers CLAUDE.md/PLAN.md patterns

## Interaction History Summary
- (auto-populated from episodic memory)
- Last interaction: (auto-populated)
```

#### 2.4 Auto-Learning for Human Profiles

The `ProfileLearner` service observes interactions and proposes profile updates:

- After each session, the SessionEndHandler passes the conversation to ProfileLearner
- ProfileLearner uses the configured fast model (Kimi K2 via Ollama) to extract: new preferences observed, corrections made, topics discussed, communication style signals
- Proposed updates are stored as pending; the bot asks for confirmation via Telegram or TUI before writing them (configurable: auto-apply for low-sensitivity updates)

---

### 3. Kanban Board

The built-in kanban board is both a data model and a TUI widget.

#### 3.1 Board Structure

```json
{
  "id": "devbot-board",
  "columns": [
    { "id": "backlog", "name": "Backlog", "wip_limit": null },
    { "id": "todo", "name": "To Do", "wip_limit": 5 },
    { "id": "in_progress", "name": "In Progress", "wip_limit": 3 },
    { "id": "review", "name": "Review", "wip_limit": 2 },
    { "id": "done", "name": "Done", "wip_limit": null }
  ]
}
```

#### 3.2 Card Model

```json
{
  "id": "card-uuid",
  "title": "Implement memory search tool",
  "description": "Build hybrid keyword + semantic search across all memory tiers",
  "column": "in_progress",
  "labels": ["memory", "core"],
  "assignee": "devbot",
  "priority": "high",
  "external_links": {
    "github_issue": "https://github.com/org/repo/issues/42",
    "gitlab_issue": null
  },
  "subtasks": [
    { "title": "Keyword search", "done": true },
    { "title": "Semantic search", "done": false },
    { "title": "Result re-ranking", "done": false }
  ],
  "created_at": "2026-03-27T10:00:00Z",
  "updated_at": "2026-03-27T14:30:00Z"
}
```

#### 3.3 GitHub/GitLab Sync

- **Pull**: Import issues matching a configurable filter (labels, milestones) as kanban cards
- **Push**: When a card is moved to "Done", optionally close the linked issue
- **Two-way**: Status changes on either side sync automatically (via polling or webhooks)

---

### 4. Telegram Integration

#### 4.1 Modes of Operation

| Mode | Description |
|---|---|
| **Notification** | Bot sends status updates (task done, PR created, errors) to a configured chat |
| **Interactive** | Human sends messages to bot via Telegram; bot processes them through the agent loop |
| **Approval** | Bot asks human for confirmation on critical actions via Telegram inline buttons |

#### 4.2 Architecture

- A `TelegramListener` runs as a Fiber alongside the TUI, polling for updates via long-polling
- Incoming messages are dispatched as Symfony events (`TelegramMessageReceived`)
- The `TelegramBridgeListener` routes them into the agent's message loop
- Responses stream back through the Telegram API
- The TUI shows a notification indicator when Telegram activity occurs

---

### 5. Skill System

The bot manages its own **skills** — reusable, composable workflows that it can create, update, and run autonomously or on demand. This is DevBot's equivalent of learned procedures.

#### 5.1 What is a Skill?

A skill is a stored workflow definition (markdown + structured metadata) that describes:

- **What** it does (description, purpose)
- **When** to use it (trigger conditions — manual, heartbeat, event-based)
- **How** to execute it (step-by-step instructions the agent follows, including which tools to call)
- **Parameters** it accepts (configurable inputs)

```markdown
# Skill: gitlab-comment-responder

## Description
Reads new comments on GitLab merge requests and generates thoughtful responses
using the coding agent.

## Trigger
heartbeat: every 15 minutes

## Parameters
- project_id: string (required) — GitLab project ID
- labels_filter: string[] (optional) — only respond to MRs with these labels

## Steps
1. Use `gitlab_tool` to fetch open MRs with new unread comments since last run
2. For each MR with new comments:
   a. Use `memory_search` to find relevant context about this project
   b. Use `filesystem_read` to read related source files if referenced
   c. Use `coding_delegate` to analyze the comment + code context
   d. Use `gitlab_tool` to post the response as a reply
3. Use `memory_add` to log which MRs were processed (avoid re-processing)
4. Use `telegram_notify` to send summary: "Responded to N comments on M MRs"
```

#### 5.2 Skill Storage

```
skills/                                # Bot-managed skill definitions
├── index.json                         # Skill registry (id, name, trigger, last_run, enabled)
├── gitlab-comment-responder.md        # Skill definition (human-readable + structured)
├── daily-standup-summary.md
├── remind-me.md
├── web-research.md
└── archive/                           # Disabled/old skills
```

#### 5.3 Skill Lifecycle

| Action | How |
|---|---|
| **Create** | Ask the bot: "Create a skill that checks GitLab comments every 15 minutes and responds" |
| **Update** | "Update the gitlab-comment-responder skill to also check issues, not just MRs" |
| **Run** | "Run the gitlab-comment-responder skill now" — or triggered automatically by heartbeat |
| **Enable/Disable** | "Disable the daily-standup-summary skill" |
| **Delete** | "Delete the remind-me skill" |
| **List** | "What skills do you have?" |

The agent parses the natural language request, generates/updates the skill markdown file, registers it in `index.json`, and wires it into the heartbeat if it has a trigger.

#### 5.4 Skill Tools

| Tool | Description |
|---|---|
| `skill_create` | Create a new skill from a description. Agent generates the markdown definition. |
| `skill_update` | Update an existing skill definition. |
| `skill_run` | Execute a skill immediately. |
| `skill_list` | List all skills with status (enabled, last_run, trigger). |
| `skill_toggle` | Enable or disable a skill. |
| `skill_delete` | Delete/archive a skill. |

---

### 6. Heartbeat System

The heartbeat is a **configurable periodic task runner** that executes skills and one-off scheduled tasks on a timer. It runs as a Fiber alongside the TUI, ticking at a configurable interval.

#### 6.1 Architecture

```
┌─────────────────────────────────────────────────────┐
│                   Heartbeat Loop                     │
│                   (Fiber, ticks every N seconds)     │
│                                                      │
│   ┌───────────────────────────────────────────────┐  │
│   │  Task Scheduler                               │  │
│   │                                                │  │
│   │  For each registered task/skill:               │  │
│   │  - Check: is it time to run? (cron/interval)   │  │
│   │  - Check: is it enabled?                       │  │
│   │  - If yes: queue for execution                 │  │
│   └───────────────┬───────────────────────────────┘  │
│                   │                                   │
│   ┌───────────────▼───────────────────────────────┐  │
│   │  Task Executor                                │  │
│   │                                                │  │
│   │  - Load skill definition                       │  │
│   │  - Build MessageBag with skill steps as prompt │  │
│   │  - Call the DevBot agent (with all tools)      │  │
│   │  - Agent executes the skill steps              │  │
│   │  - Log result to episodic memory               │  │
│   │  - Update last_run timestamp                   │  │
│   └───────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
```

#### 6.2 Task Types

**Skill-based tasks** (persistent, repeating):
- Defined as skill files, trigger configured in the skill definition
- Examples: "check GitLab comments every 15 min", "send daily standup summary at 9:00"

**One-off scheduled tasks** (ephemeral):
- Created via chat/Telegram: "Remind me tomorrow at 10:00 to review the PR"
- Stored in `heartbeat/scheduled.json`, removed after execution
- Can also be: "In 2 hours, search the web for X and send me a summary on Telegram"

**Event-driven tasks** (reactive):
- Triggered by events rather than time: "When a new issue is created in GitLab, notify me on Telegram"
- Event sources: Git polling, Telegram incoming, memory threshold, etc.

#### 6.3 Configuration

Tasks are configurable **via chat or Telegram** — the user just asks the agent:

> "Check my GitLab repo for new comments every 15 minutes and respond to them"

The agent:
1. Creates a skill definition (`skill_create` tool)
2. Registers it with a heartbeat trigger in `index.json`
3. The heartbeat loop picks it up on the next tick

> "Remind me tomorrow at 14:00 to deploy the staging environment"

The agent:
1. Creates a one-off scheduled task in `heartbeat/scheduled.json`
2. At 14:00 tomorrow, the heartbeat fires and sends a Telegram message / shows in TUI

> "Every morning at 9:00, search the web for 'Symfony AI news' and send me a summary on Telegram"

The agent:
1. Creates a skill that uses `web_search` + agent summarization + `telegram_send`
2. Registers with cron trigger `0 9 * * *`

#### 6.4 Heartbeat Configuration in `devbot.yaml`

```yaml
devbot:
    heartbeat:
        enabled: true
        tick_interval: 30              # seconds between heartbeat checks
        max_concurrent_tasks: 2        # max parallel skill executions
        task_timeout: 300              # max seconds per task execution
        scheduled_file: '%kernel.project_dir%/heartbeat/scheduled.json'
```

---

### 7. Web Search & Fetch (Ollama)

DevBot uses **Ollama's web search and fetch APIs** for internet access. These are REST endpoints at `ollama.com/api/web_search` and `ollama.com/api/web_fetch`, requiring an `OLLAMA_API_KEY` (free account).

#### 7.1 Tools

| Tool | Description |
|---|---|
| `web_search` | Search the web via Ollama API. Params: `query`, `max_results?` (default 5, max 10). Returns title, URL, content snippet per result. |
| `web_fetch` | Fetch a single web page by URL. Returns title, main content (markdown), and links found on the page. |

These are implemented as custom `#[AsTool]` classes that call the Ollama REST API directly via Symfony HttpClient. They are **not** the Ollama MCP server — they're native tools available to the agent.

#### 7.2 Why Ollama Web Search?

- **Already using Ollama** — no additional service provider needed
- **Free tier** — generous limits for a personal bot
- **Simple REST API** — `POST ollama.com/api/web_search` with auth header
- **Replaces** `symfony/ai-brave-tool` (which needs a separate Brave API key)
- **Content extraction built-in** — `web_fetch` returns clean markdown, not raw HTML

#### 7.3 Usage Examples

The agent uses these tools directly when asked, and skills can reference them:

- User: "What's new in Symfony 8.1?" → agent calls `web_search`, reads top results with `web_fetch`, summarizes
- Heartbeat skill: "Every morning, search for 'PHP security advisories' and send a digest via Telegram"
- Memory enrichment: agent discovers a topic in memory, uses `web_search` to find the latest info, updates memory

---

### 8. TUI Layout

The terminal UI uses `symfony/tui` widgets with a tabbed layout:

#### 5.1 Main Tabs

- **Chat** (default): Full-width chat panel with streaming responses, markdown rendering, tool execution indicators
- **Board**: Kanban board with columns, cards, drag-to-move (keyboard), card detail overlay
- **Memory**: Searchable memory browser — search bar at top, results below with type/tag filters, detail panel on selection
- **Logs**: Full tool execution log with timestamps, inputs/outputs, token usage

#### 5.2 Persistent Elements

- **Status bar** (bottom): Current model, token usage this session, memory stats (entries/size), active task from kanban, Telegram indicator
- **Keybinding hint bar**: Context-sensitive hotkeys

#### 5.3 Overlays

- Model selector (switch between Ollama models, delegate to Claude Code subagent)
- Card detail editor
- Memory entry viewer/editor
- Confirmation dialogs (for destructive actions)

---

## Configuration

### `config/packages/devbot.yaml`

```yaml
devbot:
    # AI model configuration (all via Ollama)
    model:
        primary: 'kimi-k2'                  # Main reasoning model (via Ollama)
        fast: 'kimi-k2'                     # Memory scoring, profile learning (same or smaller)
        embedding: 'nomic-embed-text'        # Embedding model (via Ollama)

    # Ollama connection
    ollama:
        base_url: 'http://localhost:11434'   # Local Ollama instance
        # base_url: 'http://my-server:11434' # Or remote V-Server

    # Memory system
    memory:
        short_term:
            max_turns: 50
        long_term:
            max_entries_per_topic: 100
            importance_threshold: 0.4        # Min score to persist
        episodic:
            retention_days: 90
        semantic:
            store: 'sqlite'                  # Uses symfony/ai-sqlite-store
            embedding_model: 'nomic-embed-text'  # Via Ollama
        consolidation:
            schedule: '0 3 * * *'            # Daily at 3 AM
            max_age_before_decay: 30         # Days

    # Identity
    identity:
        soul_file: '%kernel.project_dir%/identity/SOUL.md'
        identity_file: '%kernel.project_dir%/identity/IDENTITY.md'
        humans_dir: '%kernel.project_dir%/identity/humans'
        auto_learn: true
        auto_learn_confirm: false            # true = ask before saving profile updates

    # Kanban
    kanban:
        board_file: '%kernel.project_dir%/kanban/board.json'
        sync:
            github:
                enabled: true
                repo: 'org/repo'
                labels_filter: ['devbot']
                poll_interval: 300            # seconds
            gitlab:
                enabled: false

    # Telegram
    telegram:
        enabled: true
        bot_token: '%env(TELEGRAM_BOT_TOKEN)%'
        chat_id: '%env(TELEGRAM_CHAT_ID)%'
        modes: ['notification', 'interactive', 'approval']
        poll_interval: 2                      # seconds

    # Shell execution sandbox
    shell:
        allowed_commands: ['git', 'composer', 'php', 'npm', 'make', 'grep', 'find', 'cat', 'ls']
        working_directory: '%env(DEVBOT_WORKDIR)%'
        timeout: 30

    # Skill system
    skills:
        directory: '%kernel.project_dir%/skills'

    # Heartbeat system
    heartbeat:
        enabled: true
        tick_interval: 30              # seconds between heartbeat checks
        max_concurrent_tasks: 2        # max parallel skill executions
        task_timeout: 300              # max seconds per task execution
        scheduled_file: '%kernel.project_dir%/heartbeat/scheduled.json'

    # Web search & fetch (Ollama API)
    web:
        ollama_api_key: '%env(OLLAMA_API_KEY)%'    # Free account at ollama.com
        search_max_results: 5                       # Default results per search
        fetch_timeout: 15                            # Seconds per web fetch
```

### `config/packages/ai.yaml`

```yaml
ai:
    platform:
        # Primary reasoning model — Ollama with Kimi K2
        ollama:
            base_url: '%env(OLLAMA_BASE_URL)%'    # http://localhost:11434
            # Uses OllamaApiCatalog for custom/local models
            api_catalog: true

        # Claude Code — official platform bridge (spawns `claude -p` subprocess)
        # Used as subagent for complex coding tasks
        claude_code:
            binary: 'claude'                       # Path to claude CLI
            # Allowed tools, timeout etc. configured on the agent level

    agent:
        # Main DevBot agent — runs on Ollama/Kimi K2
        devbot:
            model: 'kimi-k2'                       # Ollama model name (configurable)
            prompt:
                file: '%kernel.project_dir%/config/prompts/devbot_system.md'
                include_tools: true
            tools:
                # Memory: agentic search (PR #1825 pattern — multi-hop RAG)
                - 'App\Tool\Memory\AgenticSearchTools'  # memory_search, memory_grep, memory_read, memory_prune
                # Memory: management
                - 'App\Tool\Memory\MemoryAddTool'
                - 'App\Tool\Memory\MemoryRemoveTool'
                - 'App\Tool\Memory\MemoryUpdateTool'
                - 'App\Tool\Memory\MemoryListTool'
                # Kanban tools (custom)
                - 'App\Tool\Kanban\KanbanListTool'
                - 'App\Tool\Kanban\KanbanCreateCardTool'
                - 'App\Tool\Kanban\KanbanMoveCardTool'
                - 'App\Tool\Kanban\KanbanUpdateCardTool'
                # Git tools (custom)
                - 'App\Tool\Git\GitHubTool'
                - 'App\Tool\Git\GitLabTool'
                - 'App\Tool\Git\GitStatusTool'
                - 'App\Tool\Git\GitCommitTool'
                # Telegram tools (custom)
                - 'App\Tool\Telegram\TelegramSendTool'
                - 'App\Tool\Telegram\TelegramNotifyTool'
                # Web search & fetch (Ollama API)
                - 'App\Tool\Web\WebSearchTool'
                - 'App\Tool\Web\WebFetchTool'
                # Skill management (custom)
                - 'App\Tool\Skill\SkillCreateTool'
                - 'App\Tool\Skill\SkillUpdateTool'
                - 'App\Tool\Skill\SkillRunTool'
                - 'App\Tool\Skill\SkillListTool'
                - 'App\Tool\Skill\SkillToggleTool'
                - 'App\Tool\Skill\SkillDeleteTool'
                # Heartbeat / scheduling (custom)
                - 'App\Tool\Heartbeat\ScheduleTaskTool'
                - 'App\Tool\Heartbeat\ListScheduledTool'
                - 'App\Tool\Heartbeat\CancelScheduledTool'
                # Built-in symfony/ai tools (installed via composer, auto-registered)
                # - symfony/ai-filesystem-tool    → file read/write/search/list
                # - symfony/ai-clock-tool         → current date/time
                # Shell (custom)
                - 'App\Tool\Shell\ShellExecTool'
                # Coding agent delegation (custom)
                - 'App\Tool\CodingAgent\CodingDelegateTool'
                # Identity tools (custom)
                - 'App\Tool\Identity\HumanProfileReadTool'
                - 'App\Tool\Identity\HumanProfileUpdateTool'
                # Meta tools (custom)
                - 'App\Tool\Meta\SelfReflectTool'
                - 'App\Tool\Meta\PlanTool'

        # Claude Code subagent — for complex coding tasks delegated by DevBot
        coder:
            model: 'claude-code'                   # Uses claude_code platform
            prompt:
                text: 'You are a coding agent. Execute the given task in the working directory.'

    store:
        memory_store:
            engine: 'sqlite'                        # symfony/ai-sqlite-store
            path: '%kernel.project_dir%/var/devbot_memory.sqlite'
```

### `config/packages/mcp.yaml`

```yaml
mcp:
    server:
        # DevBot itself acts as an MCP server (so external tools can query it)
        client_transports:
            stdio:
                enabled: true
            http:
                enabled: true
                host: '127.0.0.1'
                port: 8090

    client:
        # Connect to external MCP servers
        servers:
            ai-mate:
                transport: stdio
                command: './vendor/bin/mate'
                args: ['serve']
            # Add more MCP servers as needed
```

---

## Implementation Phases

### Phase 1: Foundation (Week 1-2)

**Status: Complete**

**Goal**: Basic working agent with TUI shell and chat capability.

1. **Project scaffold**
   - `composer create-project` with Symfony 8.x skeleton
   - Install: `symfony/ai-bundle`, `symfony/mcp-bundle`, `symfony/ai-ollama-platform`, `symfony/ai-claude-code-platform`, `symfony/ai-sqlite-store`
   - Add `symfony/tui` from fabpot's PR branch (see composer config below)
   - Directory structure as outlined above
   - `bin/devbot` entry point

2. **Basic TUI shell**
   - Single-tab chat interface using TUI's `EditorWidget` for input, `MarkdownWidget`/`TextWidget` for output
   - Status bar with model name and token counter
   - Keybinding: `Ctrl+C` to exit, `Enter` to send

3. **Agent integration**
   - Configure `DevBotAgent` with Ollama platform (Kimi K2)
   - Streaming responses rendered in TUI via Fibers
   - Basic system prompt from `config/prompts/devbot_system.md`

4. **Identity files (static)**
   - Create initial `SOUL.md`, `IDENTITY.md`, `identity/humans/_template.md`
   - `IdentityInjectionProcessor` loads and injects them into system prompt

5. **Web search & fetch tools**
   - Implement `WebSearchTool` and `WebFetchTool` wrapping Ollama REST API
   - Simple HttpClient calls to `ollama.com/api/web_search` and `ollama.com/api/web_fetch`
   - Agent can already search the web and read pages from day one

**Deliverable**: A working terminal chat with Kimi K2 (via Ollama) that has a personality defined by SOUL.md and can search the web.

---

### Phase 2: Memory System (Week 3-4)

**Status: Complete**

**Goal**: Full memory system with all four tiers and agent-accessible tools.

1. **Short-term store**
   - Ring buffer implementation
   - Session serialization/deserialization

2. **Long-term store**
   - Markdown file CRUD with JSON metadata index
   - Topic-based routing (projects, decisions, patterns, preferences)

3. **Episodic store**
   - JSON event logger
   - Date/type-based retrieval

4. **Semantic store**
   - SQLite vector store via `symfony/ai-sqlite-store`
   - Embedding pipeline: text → chunk → embed (Ollama/nomic-embed-text) → store
   - Zero external dependencies — single SQLite file

5. **Memory tools**
   - Implement all five tools (`search`, `add`, `remove`, `update`, `list`)
   - Register as `#[AsTool]` with proper JSON Schema descriptions
   - Test with agent in TUI

6. **Memory lifecycle**
   - `SessionEndHandler`: extract learnings from conversation using Ollama (same model)
   - `ImportanceScorer`: LLM-based with heuristic fallback
   - `ConsolidationJob`: Symfony Messenger command for periodic compaction

7. **Memory injection**
   - `MemoryInjectionProcessor`: on each agent call, search memory for relevant context and inject as a context block
   - Context budget management: allocate tokens for memory vs. conversation vs. tools

**Deliverable**: Agent that remembers across sessions, can search its own memory, and auto-extracts learnings.

---

### Phase 3: Kanban + Git Integration (Week 5-6)

**Status: Partial** -- Kanban data model, TUI widget, tools, and local git tools are done. Missing: GitHub API integration (GitHubApiService, GitHubTool), GitLab API integration (GitLabApiService, GitLabTool), issue sync.

**Goal**: Working kanban board with GitHub/GitLab sync.

1. **Kanban data model**
   - `Board`, `Column`, `Card` entities with JSON persistence
   - CRUD operations via `KanbanManager`

2. **Kanban TUI widget**
   - Column-based layout with card rendering
   - Keyboard navigation: arrows to select, `m` to move, `e` to edit, `n` to create
   - Card detail overlay

3. **Kanban tools**
   - All four tools registered with agent
   - Agent can plan work by creating/moving cards

4. **GitHub integration**
   - `GitHubApiService` using `knplabs/github-api`
   - `GitHubIssueSyncer`: import issues as cards, sync status changes
   - `GitHubTool`: agent can create issues, open PRs, add comments

5. **GitLab integration**
   - Same pattern as GitHub with `m4tthumphrey/php-gitlab-api`

6. **Git tools**
   - `GitStatusTool`, `GitCommitTool` for local git operations
   - Sandboxed to configured working directory

**Deliverable**: Agent can manage a kanban board, create GitHub issues, and work with local git.

---

### Phase 4: Telegram + Multi-Tab TUI (Week 7-8)

**Status: Partial** -- Multi-tab TUI done (Chat/Board/Memory/Logs with F1/F2/F3/F4 switching), ProfileLearner done, Logs tab done (LogWidget + ToolExecutionLogger). Missing: Telegram bridge, Telegram tools, approval flow.

**Goal**: Full TUI with all tabs and Telegram integration.

1. **Telegram bridge**
   - Bot setup via BotFather
   - `TelegramBotService` with long-polling via Fiber
   - Event-based message routing into agent loop
   - Notification sending for task updates

2. **Multi-tab TUI**
   - Tab bar: Chat | Board | Memory | Logs
   - Keyboard switching: `Ctrl+1` through `Ctrl+4`
   - Memory browser widget with search

3. **Approval flow**
   - Telegram inline keyboard for yes/no approvals
   - Agent pauses and waits for human response on critical actions
   - TUI shows pending approval indicator

4. **Profile auto-learning**
   - `ProfileLearner` extracts interaction patterns
   - Proposes updates via Telegram or TUI confirmation

**Deliverable**: Full interactive system with Telegram bot and rich TUI.

---

### Phase 5: Skill System + Heartbeat + Web (Week 9-10)

**Status: Complete**

**Goal**: Bot can manage its own skills, run periodic tasks, and use web search in workflows.

1. **Skill system core**
   - `SkillManager`: CRUD for skill markdown files + `index.json` registry
   - `SkillParser`: Parse skill markdown into structured `Skill` model
   - `SkillRunner`: Execute a skill by building a prompt from its steps and calling the agent

2. **Skill tools**
   - Implement all 6 tools: `skill_create`, `skill_update`, `skill_run`, `skill_list`, `skill_toggle`, `skill_delete`
   - Test: ask the agent "Create a skill that searches the web for PHP news every morning"
   - Agent generates the skill markdown, registers it in index.json

3. **Heartbeat loop**
   - `HeartbeatLoop` as a Fiber ticking every N seconds alongside the TUI
   - `TaskScheduler`: checks `index.json` for skills with cron/interval triggers, checks `scheduled.json` for one-off tasks
   - `TaskExecutor`: loads skill definition, builds MessageBag, calls the devbot agent with all tools available
   - Logs results to episodic memory, updates `last_run`

4. **Scheduled tasks**
   - `ScheduleTaskTool`: "Remind me tomorrow at 10:00 to review the PR"
   - `ListScheduledTool`, `CancelScheduledTool`
   - One-off tasks stored in `heartbeat/scheduled.json`, removed after execution

5. **Practical skill examples**
   - GitLab comment responder: reads new MR comments → analyzes with agent → posts response
   - Daily summary: morning digest of open issues + kanban board state → Telegram
   - Web research: periodic web search on a topic → summary to Telegram or memory

6. **Heartbeat TUI integration**
   - Status bar shows: next heartbeat tick, active tasks, last completed task
   - Logs tab shows heartbeat task executions with tool calls

**Deliverable**: Bot can create/manage its own skills, run them on a schedule, handle reminders, and execute complex multi-step workflows autonomously.

---

### Phase 6: Claude Code Subagent + MCP Server + External Tools (Week 11-12)

**Status: Partial** -- CodingDelegateTool done (delegates to Claude Code with plan/dev/auto modes). Missing: DevBot as MCP server, MCP client connections, tool discovery UI.

**Goal**: Wire up Claude Code as coding subagent, DevBot acts as MCP server and connects to external MCP servers.

1. **Claude Code subagent**
   - Wire up `coder` agent via `symfony/ai-claude-code-platform` in ai.yaml
   - Implement `CodingDelegateTool`: devbot agent can delegate complex coding tasks
   - The tool invokes the `coder` subagent with a task description + working directory
   - Results streamed back and shown in TUI

2. **DevBot as MCP server**
   - Expose memory, kanban, and identity tools via MCP
   - External agents (Claude Desktop, ai-mate) can query DevBot

3. **MCP client connections**
   - Connect to `ai-mate` for Symfony project introspection
   - Connect to other MCP servers as configured

4. **Tool discovery UI**
   - TUI overlay showing all available tools (local + MCP)
   - Enable/disable tools per session

**Deliverable**: DevBot is both an MCP server and client, extensible via external tools.

---

### Phase 7: Polish + Hardening (Week 13-14)

**Status: Partial** -- Context window management done (ContextWindowManager + ContextTruncationProcessor), unit and integration tests written (86 tests), headless mode fully implemented (SocketServer + RequestHandler + ClientCommand with SSH tunneling). Missing: comprehensive error handling, full test coverage, systemd service file, ARCHITECTURE.md.

1. **Context window management**
   - Smart truncation strategy for long conversations
   - Memory-aware compaction (keep memory context, truncate old conversation turns)

2. **Error handling**
   - Graceful degradation when APIs are unavailable
   - Retry with backoff for transient failures
   - TUI error panels instead of crashes

3. **Testing**
   - Unit tests for memory stores, kanban, identity, skills, heartbeat scheduler
   - Integration tests using `MockAgent` from symfony/ai
   - End-to-end test scenarios for skill creation → heartbeat execution → Telegram notification

4. **Documentation**
   - CLAUDE.md for the DevBot project itself
   - README with setup instructions
   - ARCHITECTURE.md with detailed system documentation

5. **V-Server deployment**
   - Systemd service file
   - tmux/screen session management
   - Log rotation
   - **Headless mode**: Telegram + heartbeat only (no TUI) — `bin/devbot run --headless`
   - Heartbeat keeps running skills autonomously, human interacts via Telegram

---

## Key Design Decisions

1. **SQLite for everything**: Single file for kanban, memory metadata, AND vector store (via `symfony/ai-sqlite-store`). Zero external dependencies, zero processes to manage. Portable — copy one file and you have everything.

2. **Ollama as primary model provider**: Runs locally, no API costs, full control. Kimi K2 (1T params MoE, 32B active) offers strong reasoning and tool calling. Configurable — swap to any Ollama model via config. Embedding model also local via Ollama.

3. **Claude Code as subagent, not primary**: The main agent loop runs on Ollama/Kimi K2. For complex multi-file coding tasks, the `CodingDelegateTool` delegates to a `coder` subagent backed by `symfony/ai-claude-code-platform`. This keeps local costs at zero for 90% of interactions while having Claude Code's full power available when needed.

4. **symfony/tui from fabpot's PR branch directly**: The TUI component is not yet merged into Symfony 8.1, but the PR branch is functional. We pin to the branch via composer repository config. Expect API changes — wrap TUI usage in thin adapter classes.

5. **Markdown files for long-term memory**: Human-readable, git-versionable, editable outside the bot. JSON metadata index alongside for fast lookups.

6. **Fibers over Messenger for real-time**: TUI requires Revolt event loop. Telegram polling, streaming responses, and animations all run as Fibers. Messenger is used for background jobs (consolidation, GC) triggered via cron.

7. **Agentic multi-hop search over single-hop RAG**: Single-hop similarity search ("query → top-K results") misses nuance. The agentic search pattern (PR #1825) lets the agent iteratively explore: semantic search for broad discovery, grep for exact facts, read for full content, prune to discard noise. Each hop can reveal new leads. The agent controls the search strategy, not a fixed pipeline.

8. **Identity files as markdown**: Simple, version-controllable, human-editable. The bot reads and writes them; humans can also edit directly. No ORM needed.

9. **Skills as agent-executed markdown**: Skills are not code — they're structured markdown files the agent reads and follows step-by-step using its tools. This means the bot can create/modify its own skills without generating PHP code. The agent IS the runtime. A skill is just a prompt with structure.

10. **Heartbeat as Fiber, not cron**: The heartbeat runs inside the same process as the TUI, sharing the Revolt event loop. This means heartbeat task results can update the TUI in real-time, and the agent has full access to all tools during execution. For headless mode (V-Server), the same loop runs without TUI rendering.

11. **Ollama web search over Brave/Tavily**: One fewer API key to manage. Ollama's web search is free-tier, the API is simple (POST with query → results), and `web_fetch` returns clean markdown. Since we're already using Ollama for everything else, it keeps the dependency tree tight.

12. **Tools + one subagent**: The main devbot agent has many tools. Complex coding is delegated to the `coder` subagent (Claude Code). Further subagent specialization (e.g., "code review agent", "research agent") can be added later — symfony/ai supports this natively via `Subagent`.

---

## CLAUDE.md (for developing DevBot)

```markdown
# DevBot Development

## Stack
- PHP 8.4+, Symfony 8.x
- symfony/tui from fabpot's PR branch (#63778) — expect API changes
- symfony/ai 0.6.x (experimental)
- Ollama (kimi-k2) as primary model, nomic-embed-text for embeddings
- symfony/ai-claude-code-platform for coding task delegation
- SQLite for all persistence (kanban, memory metadata, vector store via ai-sqlite-store)

## Commands
- Run: `php bin/devbot run`
- Run headless: `php bin/devbot run --headless` (Telegram + heartbeat only, no TUI)
- Tests: `php vendor/bin/phpunit`
- Lint: `php vendor/bin/php-cs-fixer fix`
- PHPStan: `php vendor/bin/phpstan analyse`

## Prerequisites
- Ollama running locally with `kimi-k2` and `nomic-embed-text` pulled
- Ollama API key for web search (free at ollama.com/settings/keys)
- Claude Code CLI installed and authenticated (for coding delegation)

## Conventions
- PSR-12 code style
- Strict types in every file
- Final classes by default
- #[AsTool] attribute for all agent tools
- Tools return strings (JSON for structured data)
- Memory entries are always tagged
- Skill definitions are markdown files with structured sections
- No secrets in memory, identity, or skill files

## Architecture Rules
- All AI model calls go through symfony/ai Platform — never raw HTTP (except Ollama web search API)
- Primary agent (devbot) runs on Ollama; coding subagent (coder) runs on Claude Code platform
- All tools implement the AsTool attribute pattern
- Memory search uses agentic multi-hop RAG (PR #1825 pattern), NOT single-hop similarity search
- Skills are markdown files the agent reads and executes — the agent IS the skill runner
- Heartbeat is a Fiber that ticks alongside the TUI; tasks execute by calling the agent
- TUI widgets use symfony/tui — wrap in adapters to isolate from API changes
- External API calls go through Bridge services, never directly in tools
- Identity files are the single source of truth for personality/person data

## Testing
- MockAgent for agent integration tests
- In-memory stores for unit tests
- No real API calls in tests (use mocks/fixtures)
```

---

## Getting Started (for Claude Code)

```bash
# 0. Prerequisites
# - Ollama installed and running (https://ollama.com)
# - Pull models:
ollama pull kimi-k2
ollama pull nomic-embed-text
# - Ollama API key for web search (free): https://ollama.com/settings/keys
# - Claude Code CLI installed and authenticated (https://code.claude.com)
claude --version  # verify it works

# 1. Create project
mkdir devbot && cd devbot
composer init --name="devbot/devbot" --type="project" --require="php:^8.4"

# 2. Install core Symfony dependencies
composer require symfony/framework-bundle symfony/console symfony/yaml symfony/dotenv

# 3. Install Symfony AI stack
composer require symfony/ai-bundle symfony/mcp-bundle
composer require symfony/ai-agent symfony/ai-platform symfony/ai-chat symfony/ai-store
composer require symfony/ai-ollama-platform         # Ollama (primary model + embeddings)
composer require symfony/ai-claude-code-platform     # Claude Code (coding subagent)
composer require symfony/ai-sqlite-store             # SQLite vector store

# 3b. Install built-in AI tools (auto-registered, no custom code needed)
composer require symfony/ai-filesystem-tool          # File read/write/search/list
composer require symfony/ai-clock-tool               # Current date/time
# NOTE: NOT using symfony/ai-brave-tool — using Ollama web search/fetch API instead
# NOTE: NOT using symfony/ai-similarity-search-tool — using agentic search (PR #1825)

# 4. Install symfony/tui from fabpot's PR branch
# Add to composer.json "repositories" section:
#   {
#     "type": "vcs",
#     "url": "https://github.com/symfony/symfony.git"
#   }
# Then:
#   composer require symfony/tui:dev-tui
# OR: wait for subtree split and use:
#   composer require symfony/tui:dev-main

# 5. Additional dependencies
composer require knplabs/github-api php-http/guzzle7-adapter
# composer require m4tthumphrey/php-gitlab-api  # if GitLab needed
# composer require php-telegram-bot/core          # Telegram

# 6. Dev dependencies
composer require --dev phpunit/phpunit phpstan/phpstan friendsofphp/php-cs-fixer

# 7. Create .env.local
cat > .env.local << 'EOF'
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_API_KEY=your-ollama-api-key-here
DEVBOT_WORKDIR=/path/to/your/project
TELEGRAM_BOT_TOKEN=your-token-here
TELEGRAM_CHAT_ID=your-chat-id-here
EOF

# 8. Start with Phase 1
# - Create bin/devbot
# - Set up Kernel, services, config
# - Build basic ChatWidget with TUI
# - Wire up DevBotAgent with Ollama/kimi-k2
```

---

## Open Questions / Risks

1. **symfony/tui from PR branch**: The PR (#63778) was opened March 26, 2026 and is under active review. The API will likely change. Mitigation: install from fabpot's `tui` branch, wrap all widget usage in thin adapter classes so changes only affect one layer. Once the component is merged and subtree-split, switch to the official package.

2. **symfony/ai stability**: All components are experimental (v0.6). APIs may change. Mitigation: wrap all AI interactions in thin adapter layers.

3. **Kimi K2 tool calling quality**: Kimi K2 is strong at tool calling but may not match Claude's reliability for complex multi-tool chains. Mitigation: the `CodingDelegateTool` offloads the hardest tasks to Claude Code. For the main agent, keep tools simple and focused. Add retry logic in `ToolResultProcessor`.

4. **Ollama context window**: Kimi K2 has 128K-256K context (depending on variant). With memory injection + identity + tools + conversation, budget carefully. Mitigation: strict token budgeting in `ContextWindowManager` with priority: identity (fixed) > relevant memory (dynamic) > conversation history (truncatable) > tool schemas (cacheable).

5. **symfony/ai-claude-code-platform freshness**: The package was first released v0.6.0 (March 2026) — very new. API surface is small but may change. Mitigation: the subagent pattern in ai.yaml isolates the dependency. If the bridge breaks, the main agent still works.

6. **Telegram rate limits**: Long-polling + frequent notifications can hit Telegram API limits. Mitigation: batch notifications, configurable minimum interval between sends.

7. **Ollama availability for V-Server deployment**: Running Ollama on a V-Server requires sufficient RAM (Kimi K2 quantized needs ~24GB+). Mitigation: support remote Ollama via `OLLAMA_BASE_URL` pointing to a separate GPU machine or cloud Ollama instance.
