---
paths:
  - '.claude/rules/**'
---

# Authoring Rules

- A rule says what the files its `paths` match look like: structure, naming, ownership and patterns. One concern per file (a layer such as controllers, or a domain such as websites).
- Write the specific case, not the general principle. "Postgres `like` is case-sensitive while the SQLite tests are not, so use `whereLike()`" works. "Write portable queries" gets skipped, because the surrounding code wins over a general rule.
- Each topic has one home. When the `paths` of two rules overlap, the topic goes in one of them, not both.
- Something a linter, formatter or test already enforces needs no prose. At most, name the check and what to use instead.
- Record durable conventions and non-obvious wiring, not feature behavior the code already shows. Prefer a small example when a pattern could be written two ways, or point at a real file to copy.
- A rule talks only about the files it matches. Don't point at other rule files; they load on their own.
- When a convention changes, update every affected rule in the same change.
- Multi-step "how to do X" is a skill, not a rule.

## Frontmatter

- `paths` is a YAML list of globs. Claude Code loads the rule when it reads a matching file, so keep globs specific and avoid `**` unless the rule truly applies everywhere.

```md
---
paths:
  - 'app/Http/Resources/**/*.php'
---
```
