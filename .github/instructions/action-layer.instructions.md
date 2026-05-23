---
description: "Action conventions for Laravel actions. Use when editing files under app/Actions or moving application logic out of controllers."
applyTo: "app/Actions/**/*.php"
---

# Action Layer Rules

- Keep each action focused on one application use case.
- Name application actions with the `*Action` suffix so they are visually consistent across domains, including auth-related actions.
- Keep `handle()` signatures explicit: pass models and simple scalars directly, and only introduce a DTO when that boundary should follow the DTO conventions.
- Do not pass request objects into actions or duplicate request validation inside them.
- Return models, DTOs, or simple values rather than controller-shaped HTTP payloads.
- Keep actions reusable from controllers, jobs, commands, listeners, or tests.
- Collection actions may compose single-record actions when that keeps bulk behavior consistent.
- For partial model updates backed by `Optional` DTO fields, prefer passing DTO output into `fill(...)->save()` through the model's fillable allowlist instead of rebuilding attribute arrays field by field.
- Example: if an update DTO maps output names to snake_case, use `$data->episode->fill(Arr::only($data->all(), $data->episode->getFillable()))->save();` and keep only side-effect logic in action body.
