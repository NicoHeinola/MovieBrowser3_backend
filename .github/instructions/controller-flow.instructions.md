---
description: "Controller rules for Laravel API files. Use when editing controllers under app/Http/Controllers or wiring request-controller-action endpoint flow."
applyTo: "app/Http/Controllers/**/*.php"
---

# Controller Flow Rules

## Responsibility

- Controllers coordinate HTTP flow only.
- Keep controllers thin: accept request objects, call actions, and return responses.
- Do not place persistence, validation-rule definitions, or domain branching logic in controllers.

## Endpoint Shape

- Prefer one public method per endpoint.
- Type-hint the specific request class when validation is required.
- Extract validated data in the controller before calling an action.
- Return explicit JSON responses when the endpoint is part of the API surface.

## Boundaries

- Route registration belongs in `routes/api.php`, not inside controllers.
- Request normalization belongs in request classes.
- Application logic belongs in actions.
