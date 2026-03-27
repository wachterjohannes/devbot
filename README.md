# DevBot

AI-powered development process agent built on Symfony/TUI and Symfony/AI.

## Quick Start

```bash
# Prerequisites
ollama pull kimi-k2
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

## Architecture

See [PLAN.md](PLAN.md) for full architecture, and [CLAUDE.md](CLAUDE.md) for development conventions.

## Current State: Phase 1

- Symfony 8.x project scaffold with full directory structure
- TUI chat interface (EditorWidget + MarkdownWidget) via symfony/tui
- Agent wired to Ollama/kimi-k2 via symfony/ai
- Identity system (SOUL.md, IDENTITY.md, human profiles) with injection processor
- Web search/fetch tools via Ollama REST API
- PHPStan level 6 clean, unit tests passing
