---
description: "Structure rules for backend .instructions.md files. Use when creating or editing instruction files under .github/instructions."
applyTo: ".github/instructions/**/*.instructions.md"
---

# Instruction File Rules

## File Shape

- Start each instruction with YAML frontmatter containing a plain-language `description` and a specific `applyTo` pattern.
- Keep `description` written as discovery text that names the file family and the structural concern, such as `Controller conventions for Laravel API controllers`.
- Keep `applyTo` as narrow as practical for the matched file family; avoid broad globs when the rule only fits one subtree or filename pattern.
- Use a short title and a flat list of structural rules that describe how matched files should be organized or what they should avoid owning.

## Content Rules

- Keep the guidance about file shape, ownership, and repository conventions for the matched files.
- Prefer concrete wording over abstract advice so a future edit can be checked directly against the instruction.
- Include a short example when the repository has an important pattern that could otherwise be implemented two different ways.

## Example Pattern

- Good: `Form Request conventions for API validation and authorization. Use when creating or editing files under app/Http/Requests.`
- Good: `applyTo: "app/Http/Requests/**/*.php"`
- Good: include one short example when a repository pattern could be implemented in more than one reasonable way.
- Avoid turning an instruction into a task workflow that spans routes, requests, controllers, actions, and tests.
