# Development

## Commands

```bash
php vendor/bin/phpunit              # Run all tests
php vendor/bin/phpunit --testsuite unit        # Unit tests only
php vendor/bin/phpunit --testsuite integration # Integration tests only
php vendor/bin/phpstan analyse      # Static analysis (level 6)
php vendor/bin/php-cs-fixer fix     # Fix code style (PSR-12)
```

## Project Structure

```
src/
├── Command/             # Console commands (DevBotCommand)
├── Tui/                 # TUI application and widgets
│   ├── App.php          # Root layout with tabbed views (F1/F2/F3/F4)
│   └── Widget/          # ChatWidget, KanbanWidget, MemoryBrowserWidget, LogWidget, StatusBarWidget
├── Agent/
│   ├── Processor/       # IdentityInjection, MemoryInjection, ContextTruncation
│   └── Prompt/          # ContextWindowManager
├── Memory/
│   ├── MemoryManager.php  # Facade for all stores
│   ├── Model/           # MemoryEntry, MemoryType, MemoryMetadata
│   ├── Store/           # ShortTerm, LongTerm, Episodic, Semantic
│   ├── Search/          # MemoryCorpus (agentic RAG)
│   ├── Strategy/        # RuleBasedImportanceScorer
│   └── Lifecycle/       # SessionEndHandler, GarbageCollector
├── Identity/
│   ├── IdentityManager.php
│   ├── Model/           # Soul, Identity, HumanProfile
│   └── Updater/         # ProfileLearner
├── Kanban/
│   ├── KanbanManager.php
│   └── Model/           # Board, Column, Card, CardStatus
├── Skill/               # Skill system
│   ├── SkillManager.php
│   ├── SkillParser.php
│   ├── SkillRunner.php
│   └── Model/           # Skill, SkillTrigger
├── Heartbeat/           # Heartbeat / scheduled tasks
│   ├── HeartbeatLoop.php
│   ├── TaskScheduler.php
│   ├── TaskExecutor.php
│   ├── ScheduledTaskManager.php
│   └── Model/           # ScheduledTask
├── Tool/                # Agent tools (#[AsTool])
│   ├── Memory/          # 8 memory tools (search, grep, read, prune, add, remove, update, list)
│   ├── Kanban/          # 4 kanban tools
│   ├── Git/             # 2 git tools (status, commit)
│   ├── Web/             # 2 web tools (search, fetch)
│   ├── Skill/           # 6 skill tools (create, update, run, list, toggle, delete)
│   ├── Heartbeat/       # 3 scheduled task tools (schedule, list, cancel)
│   ├── CodingAgent/     # 1 Claude Code delegation tool
│   └── Shell/           # 1 shell exec tool
├── EventListener/       # Event listeners
│   └── ToolExecutionLogger.php  # Logs tool calls to file + in-memory buffer
└── Bridge/              # External service bridges
    └── OllamaWebBridge.php
```

## Conventions

- PSR-12 code style, strict types in every file
- `final` classes by default
- `#[AsTool]` attribute for all agent tools
- Tools return arrays (auto-serialized to JSON by symfony/ai)
- All AI calls go through symfony/ai Platform — never raw HTTP

## Vendor Patches

Three patches applied via `cweagans/composer-patches`:

| Patch | Package | PR |
|-------|---------|-----|
| `ollama-ndjson-streaming.patch` | `symfony/ai-ollama-platform` | [#1827](https://github.com/symfony/ai/pull/1827) |
| `platform-ndjson-result.patch` | `symfony/ai-platform` | [#1827](https://github.com/symfony/ai/pull/1827) |
| `ai-bundle-traceable-store-managed.patch` | `symfony/ai-bundle` | [#1828](https://github.com/symfony/ai/pull/1828) |

These fix: NDJSON streaming for Ollama (instead of SSE), and `ai:store:setup` in dev mode.
Remove patches once the upstream PRs are merged and packages updated.

## Testing

- **Unit tests**: Pure PHP, no external services. Temp directories for file-based stores, mocked SemanticStore.
- **Integration tests**: Tool → Manager → Store round-trips. Git tests use a real temp repo.
- No real API calls in tests.
