# Customization Roles

Use this folder for agent definitions that delegate larger chunks of work into narrower context windows. This README is a coordination reference, not an agent.

## Primitive Boundaries

- Instructions define how a matched file family should be structured and what it should own.
- Skills define how to perform a reusable task or workflow that can span multiple file families.
- Agents define when to delegate planning, research, or review into a separate context window.

## Agent Selection

- Use `feature-researcher` for read-only investigation before implementation when file ownership, contracts, auth behavior, or schema impact are still unclear.
- Use `feature-planner` when the problem is understood well enough to map the smallest coherent implementation path across routes, requests, controllers, actions, models, migrations, or tests.
- Use `feature-reviewer` when the primary job is finding regressions, broken contracts, risky assumptions, or missing coverage in current changes.

## Examples

- Example researcher ask: inspect the existing auth endpoint ownership and current Sanctum token contract before any edits.
- Example planner ask: map the smallest file set needed to add an admin-only bulk show update endpoint.
- Example reviewer ask: review the current endpoint refactor for API regressions, validation gaps, and missing tests.

## Typical Sequences

- Unknown area: researcher -> planner -> implementation -> reviewer.
- Well-understood feature: planner -> implementation -> reviewer.
- Review-only request: reviewer.

## Guardrails

- Do not move file-structure doctrine into agent files.
- Do not turn an agent into a step-by-step implementation skill.
- Keep agent outputs concise and shaped around the specific delegation goal.
