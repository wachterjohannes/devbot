# Headless Mode & Client

## Overview

DevBot can run in two modes:

- **TUI mode** (default): interactive terminal UI with tabs
- **Headless mode**: no TUI, runs heartbeat + socket server for remote access

This enables V-Server deployment where DevBot runs continuously, executing scheduled skills and accepting commands from remote clients.

## Server (Headless)

```bash
# Start headless on default socket
php bin/devbot run --headless

# Custom socket path
php bin/devbot run --headless --socket /var/run/devbot.sock
```

What runs in headless mode:
- Heartbeat loop (scheduled skills, one-off tasks)
- Unix socket server accepting JSON commands
- Full agent with all 33 tools

The server listens on `/tmp/devbot.sock` by default.

## Client

### Local connection

```bash
# Interactive chat
php bin/devbot client

# Single message (non-interactive, for scripts)
php bin/devbot client "Show me the kanban board"
```

### Remote connection via SSH

```bash
# Connect to a remote DevBot server
php bin/devbot client --host user@my-server.com

# Custom socket path on remote
php bin/devbot client --host user@my-server.com --socket /var/run/devbot.sock
```

The client establishes an SSH tunnel to forward the Unix socket, then communicates over the tunnel transparently.

### Client commands

| Command | Description |
|---------|-------------|
| Type message + Enter | Send to DevBot agent |
| `/reset` | Reset conversation history |
| `quit` or `exit` | Disconnect |

## Protocol

Communication uses newline-delimited JSON over Unix socket:

```json
// Request
{"type": "chat", "message": "Show me the board"}

// Response
{"type": "response", "content": "Here's your kanban board..."}

// Ping/pong for connection health
{"type": "ping"}
{"type": "pong"}

// Reset conversation
{"type": "reset"}
{"type": "ok", "message": "Conversation reset"}

// Reverse tool execution (server → client)
{"type": "tool_request", "tool": "shell", "operation": "exec", "args": {"command": "ls -la"}, "id": "abc123"}
{"type": "tool_response", "output": "total 42\n...", "exit_code": 0, "error": ""}
```

## Reverse Tool Execution

When a client is connected, the server can execute operations on the client's local machine. The agent has 4 client tools:

| Tool | Description |
|------|-------------|
| `client_exec` | Run a shell command on the client (same allowlist as `shell_exec`) |
| `client_file_read` | Read a file from the client's filesystem |
| `client_file_list` | List files in a directory on the client |
| `client_claude_delegate` | Run Claude Code on the client's machine (coding, planning, analysis) |

The flow:
1. You ask DevBot (running on server) to do something with your local files
2. The agent calls `client_exec` or `client_file_read`
3. The server sends a `tool_request` through the socket to your client
4. The client executes the operation locally and returns the result
5. The agent receives the result and continues

Example:
```
You: List the PHP files in my project
DevBot: [calls client_file_list with path /home/user/project]
        [client executes locally, returns file list]
        Here are the PHP files in your project: ...
```

## V-Server Deployment

### Automated setup

The setup wizard handles everything including systemd service generation:

```bash
# Interactive: prompts for config, generates and installs systemd service
php bin/devbot setup --headless

# Non-interactive: uses env vars, skips prompts (for CI/automation)
OLLAMA_API_KEY=your-key DEVBOT_WORKDIR=/opt/projects \
  php bin/devbot setup --headless --non-interactive
```

The generated systemd service includes:
- Automatic restart on failure
- Environment variable forwarding from `.env.local`
- Security hardening (`NoNewPrivileges`, `ProtectSystem=strict`, `ReadWritePaths`)
- Ollama service dependency

### Manual systemd service

If you prefer to write it manually:

```ini
[Unit]
Description=DevBot AI Agent
After=network.target ollama.service
Wants=ollama.service

[Service]
Type=simple
User=devbot
WorkingDirectory=/opt/devbot
ExecStart=php bin/devbot run --headless --socket /var/run/devbot/devbot.sock
Restart=always
RestartSec=5
NoNewPrivileges=true
ProtectSystem=strict
ReadWritePaths=/opt/devbot/var /opt/devbot/memory /opt/devbot/kanban /opt/devbot/heartbeat /opt/devbot/skills
ReadWritePaths=/var/run/devbot

[Install]
WantedBy=multi-user.target
```

### Enable and start

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now devbot
sudo journalctl -u devbot -f   # watch logs
```

### Connect from your machine

```bash
# One-time setup: add SSH config
# Host devbot
#   HostName my-server.com
#   User devbot

# Then just:
php bin/devbot client --host devbot
```

## Architecture

```
V-Server (headless)                     Local machine (client)
┌──────────────────────┐               ┌──────────────────────┐
│  bin/devbot run       │               │  bin/devbot client    │
│    --headless         │   SSH tunnel  │    --host user@server │
│                       │◄─────────────►│                       │
│  HeartbeatLoop        │  Unix socket  │  Interactive chat     │
│  Agent + 33 tools     │  JSON proto   │  Exposes local tools: │
│  Skills/scheduler     │               │   - filesystem        │
│  SocketServer         │  tool_request │   - shell (sandboxed) │
│                       ├──────────────►│   - claude code       │
│  client_exec ─────────┤  tool_resp   │  ClientToolExecutor   │
│  client_file_read     │◄──────────────┤  executes locally     │
│  client_file_list     │               │                       │
│  client_claude_deleg. │               │                       │
└──────────────────────┘               └──────────────────────┘
```
