# Configuration

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `OLLAMA_HOST_URL` | Ollama API endpoint | `http://localhost:11434` |
| `OLLAMA_API_KEY` | Ollama API key for web search | _(required)_ |
| `DEVBOT_WORKDIR` | Working directory for git/shell tools | _(required)_ |
| `APP_SECRET` | Symfony application secret | _(change in production)_ |
| `TELEGRAM_BOT_TOKEN` | Telegram bot token (future) | _(optional)_ |
| `TELEGRAM_CHAT_ID` | Telegram chat ID (future) | _(optional)_ |

## AI Configuration

`config/packages/ai.yaml` — agent, platform, and store settings.

```yaml
ai:
    platform:
        ollama:
            endpoint: '%env(OLLAMA_HOST_URL)%'
            http_client: 'devbot.http_client.amp'  # Non-blocking I/O

    agent:
        devbot:
            model: 'kimi-k2.5:cloud'    # Any Ollama model
            prompt:
                file: '%kernel.project_dir%/config/prompts/devbot_system.md'
                include_tools: true
            tools: true

    store:
        sqlite:
            memory_store:
                dsn: 'sqlite:///%kernel.project_dir%/var/devbot_memory.sqlite'

    vectorizer:
        ollama_embeddings:
            platform: 'ai.platform.ollama'
            model: 'nomic-embed-text'
```

### Changing the Model

Edit `model` in `ai.yaml`. Any Ollama model with tool-calling support works:

```yaml
model: 'qwen3:8b'        # Smaller, faster
model: 'llama3.3:70b'    # Larger, more capable
model: 'kimi-k2.5:cloud' # Default, cloud-routed
```

## Identity Files

Located in `identity/`:

| File | Purpose |
|------|---------|
| `SOUL.md` | Bot personality, values, communication style |
| `IDENTITY.md` | Self-knowledge, capabilities, limitations |
| `humans/*.md` | Per-person profiles (preferences, context) |

Edit these directly — they're injected into the system prompt on every agent call.

## System Prompt

`config/prompts/devbot_system.md` — the base system prompt. Identity and memory context are appended automatically by input processors.
