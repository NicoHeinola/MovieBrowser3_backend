# MovieBrowser3 Backend API

Laravel 13 JSON API backend for MovieBrowser3.

## Setup

```bash
composer setup
```

## Run

```bash
composer dev
```

## Auth

Uses Sanctum bearer tokens.

```text
Authorization: Bearer <token>
```

## Endpoints

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- `GET /api/v1/watch-progress/stream` (server-sent `watch-progress` events with the user's changed `ShowWatchProgress`)

## Test

```bash
composer test
```

## AI Agent Guidance

Agent guidance lives in `.claude/` and works for both Claude Code and Copilot:

- `AGENTS.md`: repo-wide guidance. `CLAUDE.md` imports it, and `.github/copilot-instructions.md` is a symlink to it.
- `.claude/rules/`: file-specific rules. Claude loads them through `paths`, Copilot through `applyTo`.
- `.claude/skills/`: workflows. `.github/skills` is a symlink to it.
- `.claude/agents/`: planner, researcher and reviewer agents.

In VS Code, Copilot loads `AGENTS.md` by default (`chat.useAgentsMdFile`). If it doesn't pick up the rules, add `.claude/rules` to `chat.instructionsFilesLocations` in your user settings.
