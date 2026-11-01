---
description: Authoring rules for path-scoped rule files under .claude/rules. Use when creating or editing file-specific rules.
paths:
  - '.claude/rules/**'
applyTo: '.claude/rules/**'
---

# Authoring Rules

- Rules describe conventions for the files their `paths` match: structure, naming, ownership and patterns. One concern per file (a layer such as controllers, or a domain such as websites).
- Keep rules short and concrete. Prefer a small example when a pattern could be written two ways.
- Record durable conventions and non-obvious wiring, not feature behavior the code already shows.
- Rules are not workflows, review checklists or agent prompts; multi-step "how to do X" belongs in a skill.

## Frontmatter

- `paths` is a YAML list of globs. Claude Code loads the rule when it reads or edits a matching file, so keep globs specific.
- `applyTo` repeats the same globs as one comma-separated string for Copilot. Keep it in sync with `paths`.
- `description` says when the rule applies. Copilot uses it for discovery.

```md
---
description: Resource conventions for Laravel API resources. Use when creating or editing files under app/Http/Resources.
paths:
  - 'app/Http/Resources/**/*.php'
applyTo: 'app/Http/Resources/**/*.php'
---
```
