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

## Installation

```bash
git clone <repo-url> devbot && cd devbot
composer install
```

## Configuration

```bash
cp .env .env.local
```

Edit `.env.local`:

```env
OLLAMA_HOST_URL=http://localhost:11434
OLLAMA_API_KEY=your-ollama-api-key
DEVBOT_WORKDIR=/path/to/your/project
```

## First Run

Set up the vector store for memory:

```bash
php bin/devbot ai:store:setup ai.store.sqlite.memory_store
```

Start DevBot:

```bash
php bin/devbot run
```

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
