# Building Standalone Binaries

DevBot can be packaged as a standalone binary using [FrankenPHP](https://frankenphp.dev/docs/embed/). The binary embeds PHP 8.4, all extensions, and the entire application -- no PHP installation required on the target machine.

## Platforms

| Platform | Architecture | Binary |
|----------|-------------|--------|
| Linux | x86_64 | `devbot-linux-x86_64` |
| Linux | ARM64 | `devbot-linux-arm64` |
| macOS | ARM64 (Apple Silicon) | `devbot-macos-arm64` |

## Prerequisites for Building

- **Docker** (for Linux builds)
- **Go** + **FrankenPHP source** (for macOS builds)
- **Composer** (for dependency installation during prepare step)

## Build Steps

### 1. Prepare

Exports the app from git, installs production deps, strips dev files:

```bash
make prepare
```

This creates `dist/app/` with a production-ready copy.

### 2. Build

**Linux (via Docker):**

```bash
make build-linux          # x86_64
make build-linux-arm64    # ARM64
make build-all            # Both
```

**macOS (native):**

```bash
make build-macos
```

Binaries are placed in `dist/`.

## Automated Releases

Push a git tag to trigger the GitHub Actions release workflow:

```bash
git tag v0.1.0
git push origin v0.1.0
```

This builds all platforms and creates a GitHub Release with the binaries and SHA256 checksums.

## Running the Standalone Binary

Extract the tarball — it contains `devbot` (wrapper script) and `devbot-bin` (FrankenPHP runtime). Keep both in the same directory.

```bash
tar xzf devbot-linux-x86_64.tar.gz

# Setup (first run)
./devbot setup

# TUI mode
./devbot run

# Headless mode
./devbot run --headless

# Client
./devbot client --host user@server
```

## Environment Variables

The standalone binary reads environment variables the same way as the source version:

| Variable | Description | Required |
|----------|-------------|----------|
| `OLLAMA_HOST_URL` | Ollama API endpoint (default: `http://localhost:11434`) | No |
| `OLLAMA_API_KEY` | Ollama API key for web search | Yes |
| `DEVBOT_WORKDIR` | Working directory for shell/git operations | Yes |

## What's Included

The binary contains:
- PHP 8.4 interpreter (ZTS build)
- Extensions: sqlite3, pcntl, sockets, curl, openssl, mbstring, intl, opcache, and more
- All Composer dependencies (production only)
- symfony/tui component
- Vendor patches (pre-applied)
- Embedded `php.ini` with optimized settings

## What's NOT Included

- Ollama -- must be installed separately on the host
- Claude Code CLI -- must be installed separately (only needed for `claude_delegate` tool)
- TLS CA certificates -- set `SSL_CERT_FILE` env var if needed
- Data directories (memory, kanban, skills) -- created on first run
