---
description: "Structure rules for backend SKILL.md files. Use when creating or editing skill files under .github/skills."
applyTo: ".github/skills/**/SKILL.md"
---

# Skill File Rules

## File Shape

- Start each skill with YAML frontmatter containing a `name` that matches the folder name and a `description` that clearly says when the skill should trigger.
- Open with one short paragraph that states the task or concept the skill handles.
- Organize the body around action-oriented sections such as `Workflow`, `Repository-Specific Focus`, `Common Risks`, `Completion Checklist`, or `Useful Commands`.

## Content Rules

- Keep steps task-oriented so the skill explains how to execute the work across multiple files or layers.
- Keep completion checks explicit so the user can tell when the workflow is finished.
- Reference file-family instructions briefly when structural rules matter instead of restating those rules in full.
- Prefer short sections and concrete checks over long prose.

## Example Pattern

- Good workflow step: `Add or update the request class for authorization and validation before wiring the controller.`
- Good completion check: `Feature tests cover the changed API contract.`
- Good: mention the structural instruction file family only as a brief dependency, not as the place where the workflow is defined.
- Avoid using a skill as the canonical home for controller, request, model, or DTO file-structure doctrine.
