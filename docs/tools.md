# Tools Reference

DevBot has 25 agent tools available. The LLM decides when and how to call them.

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

## Git Tools

| Tool | Description |
|------|-------------|
| `git_status` | Show status, log, diff, or branches. Runs in `DEVBOT_WORKDIR`. |
| `git_commit` | Stage files and commit. Provide message and optionally specific files. |

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
