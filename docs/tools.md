# Tools Reference

DevBot has 33 agent tools available. The LLM decides when and how to call them.

## Memory Tools

### Agentic Search (multi-hop RAG)

| Tool | Description |
|------|-------------|
| `memory_search` | Semantic vector search. Returns ranked snippets. Auto-deduplicates across calls. |
| `memory_grep` | Keyword search across memory content. Use for exact terms, names, dates. |
| `memory_read` | Read full content of a memory entry by ID. Adds to working set. |
| `memory_prune` | Exclude an entry from future results this session. Keeps search focused. |

### Memory CRUD

| Tool | Description |
|------|-------------|
| `memory_add` | Store a new memory. Types: `long_term` (facts) or `episodic` (events). |
| `memory_remove` | Permanently delete a memory entry. |
| `memory_update` | Update content, tags, importance, or topic. |
| `memory_list` | List entries filtered by type, topic, or tags. |

### Memory Types

- **long_term** — Persistent facts: decisions, patterns, preferences. Stored as markdown files.
- **episodic** — Event log: what happened when. Stored as dated JSON files.
- **semantic** — All persisted entries are auto-indexed for vector search.
- **short_term** — Current session context (in-memory ring buffer, not exposed via tools).

## Kanban Tools

| Tool | Description |
|------|-------------|
| `kanban_list` | Show the full board: all columns with cards, WIP limits. |
| `kanban_create_card` | Create a new task card. Set title, status, labels, priority, assignee. |
| `kanban_move_card` | Move a card between columns. Respects WIP limits. |
| `kanban_update_card` | Update card details: title, description, labels, priority. |

### Board Columns

| Column | WIP Limit |
|--------|-----------|
| Backlog | — |
| To Do | 5 |
| In Progress | 3 |
| Review | 2 |
| Done | — |

## Claude Code Delegation

| Tool | Description |
|------|-------------|
| `claude_delegate` | Delegate a complex task to Claude Code (`claude -p` subprocess). Supports plan/dev modes, model selection, context injection. |

### Modes

| Mode | Permission Mode | Use For |
|------|----------------|---------|
| `plan` | `--permission-mode plan` | Architecture, analysis, code review, estimations (read-only) |
| `dev` | `--permission-mode acceptEdits` | Coding, refactoring, debugging, file modifications (default) |
| `auto` | `--permission-mode auto` | Let Claude decide what permissions it needs |

### Models

| Model | Best For |
|-------|---------|
| `sonnet` | Default — fast, capable, cost-effective |
| `opus` | Complex architecture, deep analysis |
| `haiku` | Quick tasks, simple edits |

## Git Tools

| Tool | Description |
|------|-------------|
| `git_status` | Show status, log, diff, or branches. Runs in `DEVBOT_WORKDIR`. |
| `git_commit` | Stage files and commit. Provide message and optionally specific files. |

## GitHub & GitLab Tools

| Tool | Description |
|------|-------------|
| `github` | Interact with GitHub via the `gh` CLI: list issues/PRs, view details, read and post comments. Requires `gh` installed and authenticated. |
| `gitlab` | Interact with GitLab via the `glab` CLI: list issues/MRs, view details, read and post comments. Requires `glab` installed and authenticated. |

### GitHub Operations

| Operation | Description |
|-----------|-------------|
| `list_issues` | List issues (filterable by state: open, closed, all) |
| `list_prs` | List pull requests |
| `view_issue` | View a single issue by number |
| `view_pr` | View a single PR by number |
| `list_comments` | List comments on an issue or PR |
| `post_comment` | Post a comment on an issue or PR |

### GitLab Operations

| Operation | Description |
|-----------|-------------|
| `list_issues` | List issues (filterable by state: opened, closed, all) |
| `list_mrs` | List merge requests |
| `view_issue` | View a single issue by number |
| `view_mr` | View a single MR by number |
| `list_comments` | List comments on an issue |
| `post_comment` | Post a comment on an issue |
| `mr_comment` | Post a comment on a merge request |

## Shell Tools

| Tool | Description |
|------|-------------|
| `shell_exec` | Execute a shell command in the working directory. Sandboxed to an allowlist of safe commands. |

### Allowed Commands

`git`, `composer`, `php`, `npm`, `npx`, `node`, `make`, `grep`, `find`, `cat`, `ls`, `wc`, `head`, `tail`, `sort`, `uniq`, `diff`, `echo`, `mkdir`, `touch`, `pwd`, `date`, `which`

Dangerous commands (`rm`, `sudo`, `curl`, `chmod`, etc.) are blocked.

## Web Tools

| Tool | Description |
|------|-------------|
| `web_search` | Search the web via Ollama API. Returns titles, URLs, snippets. |
| `web_fetch` | Fetch a page by URL. Returns clean markdown content. |

## Skill Tools

| Tool | Description |
|------|-------------|
| `skill_create` | Create a new skill with name, description, steps, trigger, and schedule. |
| `skill_update` | Update a skill's description, steps, trigger, or schedule. |
| `skill_run` | Execute a skill immediately by ID. |
| `skill_list` | List all skills with status, trigger, and last run time. |
| `skill_toggle` | Enable or disable a skill. |
| `skill_delete` | Delete a skill (moved to archive). |

## Heartbeat / Scheduled Task Tools

| Tool | Description |
|------|-------------|
| `schedule_task` | Schedule a one-off task or reminder for a specific time. |
| `list_scheduled` | List all upcoming scheduled tasks. |
| `cancel_scheduled` | Cancel a scheduled task by ID. |

## Client Tools (Headless Mode)

These tools are only available when a client is connected to the headless server via `bin/devbot client`. They execute operations on the client's local machine through reverse tool execution.

| Tool | Description |
|------|-------------|
| `client_exec` | Execute a shell command on the connected client's machine. Same allowlist as `shell_exec`. |
| `client_file_read` | Read a file from the connected client's filesystem. |
| `client_file_list` | List files in a directory on the connected client's machine. |
| `client_claude_delegate` | Run Claude Code on the connected client's machine. Claude has access to the client's local filesystem and dev environment. Supports plan/dev/auto modes and model selection. |

See [headless.md](headless.md) for details on the reverse tool execution protocol.

## Usage Examples

Ask DevBot naturally — it picks the right tools:

- _"Remember that we decided to use PostgreSQL for the new project"_ → `memory_add`
- _"What do we know about the auth system?"_ → `memory_search` / `memory_grep`
- _"Create a task to fix the login bug"_ → `kanban_create_card`
- _"Show me the board"_ → `kanban_list`
- _"What changed in the repo?"_ → `git_status`
- _"Search the web for Symfony 8 release notes"_ → `web_search`
- _"Create a skill that checks for PHP news every morning"_ → `skill_create`
- _"What skills do you have?"_ → `skill_list`
- _"Run the news digest skill now"_ → `skill_run`
- _"Remind me tomorrow at 10:00 to review the PR"_ → `schedule_task`
- _"Refactor the UserController to use dependency injection"_ → `claude_delegate` (mode: dev)
- _"Analyze the codebase and propose an architecture for the new API"_ → `claude_delegate` (mode: plan)
- _"Run composer install and show me the output"_ → `shell_exec`
- _"Find all PHP files that contain 'deprecated'"_ → `shell_exec`
- _"List the open issues on our GitHub repo"_ → `github` (operation: list_issues)
- _"Post a comment on PR #42 saying the fix looks good"_ → `github` (operation: post_comment)
- _"Show me the latest merge requests on GitLab"_ → `gitlab` (operation: list_mrs)
- _"Run the test suite on the client's machine"_ → `client_exec`
- _"Ask Claude on the client to review the auth module"_ → `client_claude_delegate`
