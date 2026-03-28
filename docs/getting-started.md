# Getting Started

## Prerequisites

- **PHP 8.4+**
- **Ollama** running locally — [ollama.com](https://ollama.com)
- **Ollama API key** (free) for web search — [ollama.com/settings/keys](https://ollama.com/settings/keys)
- **Claude Code CLI** installed and authenticated (for coding delegation) — [claude.ai/download](https://claude.ai/download)
- **gh CLI** (optional) for GitHub integration — [cli.github.com](https://cli.github.com)
- **glab CLI** (optional) for GitLab integration — [gitlab.com/gitlab-org/cli](https://gitlab.com/gitlab-org/cli)

Pull the required models:

```bash
ollama pull kimi-k2.5:cloud    # Main reasoning model
ollama pull nomic-embed-text   # Embedding model for memory search
```

## Option A: Standalone Binary (recommended)

Download the latest release for your platform:

```bash
# Linux x86_64
curl -Lo devbot https://github.com/wachterjohannes/devbot/releases/latest/download/devbot-linux-x86_64

# Linux ARM64
curl -Lo devbot https://github.com/wachterjohannes/devbot/releases/latest/download/devbot-linux-arm64

# macOS ARM64 (Apple Silicon)
curl -Lo devbot https://github.com/wachterjohannes/devbot/releases/latest/download/devbot-macos-arm64

chmod +x devbot
```

Run the interactive setup wizard:

```bash
# Guided setup: configures .env.local, creates directories, initializes vector store
./devbot php-cli bin/devbot setup

# Run
./devbot php-cli bin/devbot run
```

No PHP installation required -- everything is bundled in the binary.

## Option B: From Source (for development)

```bash
git clone https://github.com/wachterjohannes/devbot.git && cd devbot
composer install
```

Run the interactive setup wizard:

```bash
# Guided setup: configures .env.local, creates directories, initializes vector store
php bin/devbot setup

# Run
php bin/devbot run
```

## Server Deployment (headless)

For deploying DevBot on a V-Server with systemd:

```bash
# Setup with systemd service generation
php bin/devbot setup --headless

# Or for automated/CI deployments (uses env vars, no prompts)
php bin/devbot setup --headless --non-interactive
```

The `--headless` flag additionally:
- Generates a systemd service file with security hardening
- Optionally installs it (requires sudo)
- Configures socket path and environment forwarding

See [Headless Mode & Client](headless.md) for full server/client documentation.

## TUI Controls

| Key | Action |
|-----|--------|
| **Ctrl+Enter** | Send message |
| **Ctrl+Q** | Quit |
| **F1** | Chat tab |
| **F2** | Kanban board tab |
| **F3** | Memory browser tab |
| **F4** | Tool execution logs tab |
| **Arrow keys** | Navigate lists (board, memory) |
| **F6** | Cycle focus between widgets |
