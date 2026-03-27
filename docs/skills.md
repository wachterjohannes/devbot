# Skills & Heartbeat

## Skills

Skills are reusable, composable workflows that DevBot can create, update, and run. They're stored as markdown files — the agent reads and follows the steps using its tools.

### What is a Skill?

A skill is a markdown file in `skills/` that defines:
- **What** it does (description)
- **When** to run (trigger: manual, cron, interval)
- **How** to execute (ordered steps using tools)
- **Parameters** it accepts

### Example Skill

```markdown
# Skill: php-news-digest

## Description
Search the web for recent PHP news and store a summary in memory.

## Trigger
cron: 0 9 * * *

## Parameters
- topic: string (required)

## Steps
1. Use `web_search` to find recent news about the topic
2. Summarize the top 3 results
3. Use `memory_add` to store the summary with tags ["news", "digest"]
```

### Managing Skills

Ask DevBot naturally:

- _"Create a skill that checks for PHP security advisories every morning"_
- _"List my skills"_
- _"Run the news digest skill"_
- _"Disable the daily summary skill"_
- _"Delete the old research skill"_

Or use the tools directly: `skill_create`, `skill_list`, `skill_run`, `skill_toggle`, `skill_delete`, `skill_update`.

### Trigger Types

| Trigger | Schedule Format | Example |
|---------|----------------|---------|
| `manual` | — | Run only when asked |
| `cron` | Cron expression | `0 9 * * *` (daily at 9:00) |
| `interval` | Seconds | `900` (every 15 minutes) |

### Storage

```
skills/
├── index.json              # Registry (id, trigger, enabled, last_run)
├── php-news-digest.md      # Skill definition
├── remind-me.md
└── archive/                # Deleted skills
```

## Heartbeat

The heartbeat is a background loop that runs alongside the TUI, checking for due tasks every 30 seconds.

### What it Does

1. Checks `skills/index.json` for skills with cron/interval triggers
2. Checks `heartbeat/scheduled.json` for one-off tasks that are due
3. Executes due items by calling the agent with the skill steps as prompt
4. Logs results to episodic memory

### Scheduled Tasks

One-off tasks for reminders and deferred work:

- _"Remind me tomorrow at 14:00 to deploy the staging environment"_
- _"In 2 hours, search the web for Symfony AI updates"_

Tools: `schedule_task`, `list_scheduled`, `cancel_scheduled`.

Scheduled tasks are stored in `heartbeat/scheduled.json` and removed after execution.

### Configuration

The heartbeat tick interval is configured in `config/services.yaml`:

```yaml
App\Heartbeat\HeartbeatLoop:
    arguments:
        $tickInterval: 30  # seconds between checks
```
