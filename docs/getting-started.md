# Getting Started

## Prerequisites

- **PHP 8.4+**
- **Ollama** running locally — [ollama.com](https://ollama.com)
- **Ollama API key** (free) for web search — [ollama.com/settings/keys](https://ollama.com/settings/keys)

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
| **F6 / Shift+F6** | Cycle focus between widgets |
