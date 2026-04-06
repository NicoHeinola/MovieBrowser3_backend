---
description: "Authoring rules for project SKILL.md files. Use when creating or editing backend skills under .github/skills."
applyTo: '.github/skills/**'
---

# Authoring Skills

## What Skills Are For

- Skills encode concept-driven workflows and domain knowledge that span multiple steps or file types.
- Use a skill when guidance needs to connect routes, requests, controllers, actions, models, migrations, and tests rather than one file family.
- Keep skill descriptions explicit about when they should trigger automatically.
- Prefer short workflow sections and concrete completion checks over long prose.

## When To Use An Instruction Instead

- Use an instruction when the rule can be expressed as `files matching X should look like Y`.
- If the guidance only applies to one file family and does not need cross-file reasoning, keep it as an instruction instead of a skill.