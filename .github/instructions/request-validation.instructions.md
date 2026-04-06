---
description: "Request validation rules for Laravel Form Request files. Use when creating or editing app/Http/Requests classes for API validation, normalization, or authorization."
applyTo: "app/Http/Requests/**/*.php"
---

# Request Validation Rules

## Responsibility

- Request classes own validation rules, normalization, and authorization decisions for the endpoint.
- Keep request classes focused on HTTP input concerns.
- Do not perform persistence, token creation, queries with side effects, or broader application logic here.

## Validation

- Prefer explicit validation rules over broad acceptance.
- Normalize input in `prepareForValidation()` when the endpoint depends on it.
- Keep rule definitions close to the request class instead of duplicating them in controllers or actions.

## Boundaries

- Controllers should consume `validated()` data from the request.
- Actions should receive clean values or models, not the request object itself.
- If a normalization rule is endpoint-specific, keep it in the request class rather than in a shared helper by default.
