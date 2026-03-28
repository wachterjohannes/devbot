# Real-World Use Cases

## Daily Development Workflow

### Morning Standup Prep

> "Create a skill that every morning at 8:30 checks my kanban board, lists in-progress tasks, and stores a summary in memory"

DevBot creates a cron skill that runs `kanban_list`, formats the board state, and saves it via `memory_add`. You start your day with context already loaded.

### Code Review Reminders

> "Remind me tomorrow at 10:00 to review the authentication PR"

DevBot schedules a one-off task. When it fires, the heartbeat logs it to episodic memory so you have a record.

### Research While You Work

> "Search the web for Symfony 8.1 release notes and remember the key changes"

DevBot calls `web_search`, reads the results with `web_fetch`, and stores a summary in long-term memory tagged `["symfony", "release"]`. Next time you ask about Symfony 8.1, it finds the memory automatically.

## Project Management

### Task Planning

> "Create a card for implementing the user export feature, high priority, assign it to me"

```
→ kanban_create_card("Implement user export", status: "todo", priority: "high", assignee: "johannes", labels: ["feature"])
```

### Sprint Review

> "Show me the board and move the auth fix to done"

DevBot calls `kanban_list` to show all columns, then `kanban_move_card` to update the card. WIP limits are enforced automatically.

### Progress Tracking

> "What did we work on this week?"

DevBot searches episodic memory for recent events and the kanban board for cards moved to done this week.

## Knowledge Management

### Architectural Decisions

> "Remember: we decided to use SQLite for all persistence because it's zero-dependency and portable"

Stored in long-term memory under `decisions/` with tags `["architecture", "database"]`. When someone later asks "why do we use SQLite?", DevBot finds this decision via semantic search.

### Code Patterns

> "Remember that all tools must return arrays, never objects"

Stored as a pattern. DevBot's memory injection surfaces this when relevant conversations arise.

### Onboarding Context

> "Remember that Johannes prefers PSR-12, small commits, and reviewing plans before execution"

This enriches the human profile and gets injected into every conversation via the identity system.

## Automated Workflows

### Periodic Web Research

> "Create a skill that every 15 minutes searches for PHP security advisories and stores any new findings in memory"

```markdown
# Skill: php-security-watch
## Trigger
interval: 900
## Steps
1. Use web_search for "PHP security advisory CVE 2026"
2. Use memory_grep to check if we already know about these
3. Use memory_add to store any new advisories with tags ["security", "php"]
```

### Git Status Monitoring

> "Create a skill that checks git status every hour and creates a kanban card if there are uncommitted changes"

The skill runs `git_status`, checks if the output is non-empty, and uses `kanban_create_card` to flag it.

### Meeting Notes Archive

> "Remember the key points from today's meeting: we agreed on PostgreSQL, Johannes will handle the migration, deadline is April 15"

Stored in long-term memory with tags `["meeting", "decisions"]`. Creates episodic entries with timestamps. DevBot can later answer "when did we set the deadline?" or "who is handling the migration?".

## Multi-Step Investigations

### Debugging Context

> "What do we know about the authentication system? Search memory and the web."

DevBot uses agentic multi-hop search:
1. `memory_search("authentication")` — finds related memories
2. `memory_grep("auth")` — finds exact mentions
3. `memory_read(id)` — reads full entries for promising hits
4. `memory_prune(id)` — discards irrelevant results
5. `web_search("Symfony authentication best practices")` — supplements with web knowledge

### Project Recap

> "Summarize everything we know about the sulu.ai project"

DevBot searches all memory tiers — long-term facts, episodic events, semantic similarity — and assembles a comprehensive summary.

## Composing Skills

Skills can reference other tools, creating complex workflows:

> "Create a skill that does a weekly code quality check: run git status, check the kanban board for stale in-progress cards older than 3 days, and store a report in memory"

The agent follows the steps using `git_status`, `kanban_list`, date arithmetic, and `memory_add`. The heartbeat runs it every Monday at 9:00.

## Shell Commands

### Running Project Scripts

> "Run composer install and show me the output"

DevBot calls `shell_exec` with `composer install`. Output is returned directly.

### Inspecting Files

> "Find all PHP files that contain 'deprecated'"

```
-> shell_exec("grep -r 'deprecated' --include='*.php' .")
```

### Build & Test

> "Run the test suite and tell me if anything fails"

```
-> shell_exec("php vendor/bin/phpunit")
```

> "Check for code style issues"

```
-> shell_exec("php vendor/bin/php-cs-fixer fix --dry-run --diff")
```

### Quick Checks

> "How many lines of PHP code do we have?"

```
-> shell_exec("find src -name '*.php' | xargs wc -l | tail -1")
```

> "Show me the git log for the last week"

```
-> shell_exec("git log --oneline --since='1 week ago'")
```

## Claude Code Delegation

### Architecture Planning

> "Analyze the current codebase and propose an architecture for adding webhook support"

DevBot delegates to Claude Code in **plan mode** (read-only). Claude reads the codebase, understands the patterns, and returns a detailed architecture proposal — without modifying any files.

### Code Implementation

> "Implement the webhook listener based on the plan we just discussed"

DevBot delegates in **dev mode** (acceptEdits). Claude Code creates files, modifies existing code, and runs tests. The result is returned to DevBot's chat.

### Code Review

> "Review the changes in src/Kanban/ for potential issues"

Plan mode again — Claude reads the code, analyzes patterns, checks for bugs, and reports findings without touching anything.

### Refactoring with Context

> "Refactor the memory stores to use a common interface. Here's the context: we have ShortTermStore, LongTermStore, EpisodicStore, and SemanticStore."

DevBot passes the task plus context to Claude Code in dev mode. Claude understands the full picture and makes coordinated changes across files.

### Quick Fixes with Haiku

> "Add the missing return type to SkillParser::extractName — use haiku, it's a simple fix"

For trivial changes, DevBot can delegate to the `haiku` model — faster and cheaper than sonnet/opus.

### Deep Analysis with Opus

> "Use opus to analyze our memory system's scalability characteristics and suggest improvements for 100k+ entries"

For complex reasoning tasks, DevBot can specify the `opus` model for maximum capability.

### Combined Workflows

> "Create a skill that every Friday at 17:00 asks Claude to review all commits from this week and store a summary in memory"

A heartbeat skill that:
1. Uses `git_status` to get the week's commit log
2. Delegates to Claude Code (plan mode) for analysis
3. Stores the review in memory via `memory_add`
4. Creates a kanban card if issues were found
