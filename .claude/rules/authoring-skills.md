---
paths:
  - '.claude/skills/**'
---

# Authoring Skills

- A skill is a **moment**: one thing the user asks for in this repo ("add a resource like show-sections", "add a column"). It holds every step that moment needs, in order, so one load is enough.
- No one-liner skills: a line that small goes into a rule or `CLAUDE.md`. A generic moment (tickets, reviews, tests, push, releases) is a global skill; this repo only adds its specifics in `CLAUDE.md` and the rules.
- Frontmatter: `name` matches the folder. `description` is in the third person ("Scaffolds…"): what it does, the phrases that trigger it, and what it is not for.
- Short numbered steps and a "Done when" check. A step says what to do at that moment ("read `CreateCategoryAction` before writing the new create action"), not a general principle.
- Name real files to copy from instead of restating rule content or templates. Don't mirror a rule with a longer checklist.
