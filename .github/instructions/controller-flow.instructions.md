---
description: "Controller conventions for Laravel API controllers. Use when editing files under app/Http/Controllers or wiring an endpoint through a controller."
applyTo: "app/Http/Controllers/**/*.php"
---

# Controller Flow Rules

- Controllers coordinate HTTP flow only.
- Keep public methods thin: accept request and model dependencies, call actions or query builders, and return explicit API responses.
- Keep authorization at the HTTP boundary with route middleware plus policy checks from controller helpers such as `$this->authorize(...)`; do not inline Gate logic in multiple styles across controllers.
- Return controller-managed API payloads through resource classes instead of raw `response()->json([...])` arrays.
- Prefer lean success responses; omit generic `message` fields unless the contract has a concrete reason to carry one.
- Type-hint the specific request class when validation is required and extract `validated()` data before calling an action.
- Prefer route-model binding over manual lookup queries in controllers when the route already identifies a model.
- If the action boundary should use a DTO, pass one that follows the DTO conventions.
- Do not place validation rules, persistence workflows, or domain branching logic in controllers.
