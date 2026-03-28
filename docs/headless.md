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
- Full agent with all 27 tools

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
```

## V-Server Deployment

### systemd service

```ini
[Unit]
Description=DevBot AI Agent
After=network.target

[Service]
Type=simple
User=devbot
WorkingDirectory=/opt/devbot
ExecStart=/usr/bin/php bin/devbot run --headless
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
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
│  Agent + 27 tools     │  JSON proto   │                       │
│  Skills/scheduler     │               │                       │
│  SocketServer         │               │                       │
└──────────────────────┘               └──────────────────────┘
```
