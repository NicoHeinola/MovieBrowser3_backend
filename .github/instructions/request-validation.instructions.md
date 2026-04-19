---
description: "Form Request conventions for API validation. Use when creating or editing files under app/Http/Requests."
applyTo: "app/Http/Requests/**/*.php"
---

# Request Validation Rules

- Requests own endpoint validation rules only; do not put authorization logic in `authorize()`.
- Keep authorization in policies and wire it through the controller or route layer.
- Keep requests focused on HTTP input concerns; do not perform persistence, token creation, side-effecting queries, or broader application logic here.
- Prefer explicit rule arrays and custom rule objects for complex validation.
- When validation depends on the routed subject, prefer inspecting the already bound route model instead of re-querying for the same record inside the request.
- Reach for `prepareForValidation()` or `withValidator()` only when the endpoint genuinely needs request-level normalization or validation hooks.
- Controllers should consume `validated()` data, and actions should receive clean values, models, or a DTO rather than the request object.
