---
description: "Controller conventions for Laravel API controllers. Use when editing files under app/Http/Controllers or wiring an endpoint through a controller."
applyTo: |
    app/Http/Controllers/*.php
    app/Http/Controllers/**/*.php
---

# Controller Flow Rules

- Controllers coordinate HTTP flow only.
- Keep public methods thin: accept request and model dependencies, call actions or query builders, and return explicit API responses.
- Return controller-managed API payloads through resource classes instead of raw `response()->json([...])` arrays.
- Prefer lean success responses; omit generic `message` fields unless the contract has a concrete reason to carry one.
- Type-hint the specific request class when validation is required and extract `validated()` data before calling an action.
- Build DTOs only when the action boundary benefits from grouped scalar or array payloads.
- Do not place validation rules, persistence workflows, or domain branching logic in controllers.
