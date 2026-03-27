# DevBot

AI-powered development process agent built on Symfony/TUI and Symfony/AI.
See `PLAN.md` for full architecture, directory structure, config, and implementation phases.

## Stack
- PHP 8.4+, Symfony 8.x
- symfony/tui from fabpot's PR branch (#63778, `dev-tui`)
- symfony/ai 0.6.x (experimental — all components)
- Ollama (kimi-k2) as primary model, nomic-embed-text for embeddings
- symfony/ai-claude-code-platform for coding task delegation (subagent)
- SQLite for all persistence (kanban, memory, vector store via ai-sqlite-store)
- Ollama web search/fetch API for internet access

## Commands
- Run: `php bin/devbot run`
- Run headless: `php bin/devbot run --headless`
- Tests: `php vendor/bin/phpunit`
- Lint: `php vendor/bin/php-cs-fixer fix`
- PHPStan: `php vendor/bin/phpstan analyse`

## Prerequisites
- Ollama running with `kimi-k2` and `nomic-embed-text` pulled
- Ollama API key (free) for web search: https://ollama.com/settings/keys
- Claude Code CLI installed and authenticated

## Conventions
- PSR-12, strict types in every file, final classes by default
- `#[AsTool]` attribute for all agent tools
- Tools return strings (JSON for structured data)
- Memory entries are always tagged
- Skill definitions are markdown files with structured sections
- No secrets in memory, identity, or skill files

## Architecture Rules
- All AI model calls go through symfony/ai Platform — never raw HTTP (except Ollama web search API)
- Primary agent (devbot) runs on Ollama; coding subagent (coder) runs on Claude Code platform
- Memory search uses agentic multi-hop RAG (search/grep/read/prune), NOT single-hop similarity
- Skills are markdown files the agent reads and executes — the agent IS the runtime
- Heartbeat is a Fiber alongside TUI; tasks execute by calling the agent with all tools
- TUI widgets use symfony/tui — wrap in thin adapters to isolate from API changes
- External API calls go through Bridge services, never directly in tools
- Identity files (SOUL.md, IDENTITY.md, humans/*.md) are the single source of truth

## Testing
- MockAgent for agent integration tests
- In-memory stores for unit tests
- No real API calls in tests

## Key Packages
```
symfony/ai-bundle
symfony/ai-agent
symfony/ai-platform
symfony/ai-chat
symfony/ai-store
symfony/ai-ollama-platform
symfony/ai-claude-code-platform
symfony/ai-sqlite-store
symfony/ai-filesystem-tool
symfony/ai-clock-tool
symfony/mcp-bundle
```

## Current Phase
Start with **Phase 1** from PLAN.md.
