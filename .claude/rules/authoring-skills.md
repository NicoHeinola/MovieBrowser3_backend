---
description: Authoring rules for project SKILL.md files. Use when creating or editing skills under .claude/skills.
paths:
  - '.claude/skills/**'
applyTo: '.claude/skills/**'
---

# Authoring Skills

- Skills are reusable workflows that span several files or layers. If the guidance reads as "files matching X must look like Y", it is a rule instead.
- Frontmatter: `name` matches the folder, `description` states the capability and the situations that should trigger it.
- Use short numbered steps and an explicit "Done when" check. Name reference files to copy from instead of restating rule content or templates.
- Do not mirror a rule with a longer checklist, and do not wrap an agent prompt in a skill.
