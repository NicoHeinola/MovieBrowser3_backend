---
description: "Form Request conventions for API validation and authorization. Use when creating or editing files under app/Http/Requests."
applyTo: "app/Http/Requests/**/*.php"
---

# Request Validation Rules

- Requests own endpoint authorization and validation rules.
- Keep requests focused on HTTP input concerns; do not perform persistence, token creation, side-effecting queries, or broader application logic here.
- Prefer explicit rule arrays and custom rule objects for complex validation.
- Reach for `prepareForValidation()` or `withValidator()` only when the endpoint genuinely needs request-level normalization or validation hooks.
- Controllers should consume `validated()` data, and actions should receive clean values, models, or a DTO rather than the request object.
