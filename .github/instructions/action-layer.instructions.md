---
description: "Action-layer rules for Laravel Actions. Use when editing files under app/Actions or moving application logic out of controllers into reusable actions."
applyTo: "app/Actions/**/*.php"
---

# Action Layer Rules

## Responsibility

- Actions own application logic for one task.
- Keep each action focused on a single use case.
- Prefer method inputs such as validated arrays, scalars, DTO-like values, or models over HTTP request objects.

## Boundaries

- Do not define controller-only response handling inside actions for new work.
- Do not duplicate validation rules that already belong to request classes.
- Keep framework-specific HTTP concerns out of the action unless the task explicitly requires an action to run as an HTTP endpoint.

## Reuse

- Actions should be callable from controllers and remain suitable for reuse from jobs, commands, listeners, or tests.
- Collection-level actions may orchestrate single-record actions when that keeps bulk behavior consistent with the single-item path.
- Throw clear exceptions when business rules fail rather than returning ambiguous error payloads from deep inside the action.
