---
description: "Action conventions for Laravel actions. Use when editing files under app/Actions or moving application logic out of controllers."
applyTo: "app/Actions/**/*.php"
---

# Action Layer Rules

- Keep each action focused on one application use case.
- Keep `handle()` signatures explicit: pass models, simple scalars, or a DTO only when grouping related scalar or array fields helps the boundary.
- Do not pass request objects into actions or duplicate request validation inside them.
- Return models, DTOs, or simple values rather than controller-shaped HTTP payloads.
- Keep actions reusable from controllers, jobs, commands, listeners, or tests.
- Collection actions may compose single-record actions when that keeps bulk behavior consistent.
