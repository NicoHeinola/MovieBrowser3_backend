---
name: backend-endpoint-workflow
description: "Build or refactor a Laravel API endpoint with the project-standard request-controller-action flow. Use when adding routes, request validation, thin controllers, action logic, auth-protected endpoints, or feature tests for API behavior."
---

# Backend Endpoint Workflow

Use this skill when a task adds or refactors one API endpoint or a small related slice.

## Workflow

1. Confirm the route shape, middleware, and expected API contract.
2. Add or update the request class for authorization and validation; prefer explicit rules or custom rule objects before request lifecycle hooks.
3. Keep the controller thin, extract validated data there, and build a DTO only when grouping related scalar or array fields helps the action boundary.
4. Keep application logic inside the action and keep its return value HTTP-agnostic.
5. Return controller-managed API payloads through resources so outward response shaping stays in the resource layer, and keep success payloads lean unless the contract needs extra metadata.
6. For index-style endpoints, reuse model query traits and explicit eager loading rather than ad hoc query parsing in the controller.
7. Use dedicated collection requests and actions for bulk operations.
8. Add or update feature tests for the changed contract.
9. Run the narrowest relevant Pest command.

## Boundary Checks

- Routes register endpoints, they do not hold endpoint logic.
- Request classes own input validation and authorization, not actions.
- Controllers orchestrate HTTP flow, they do not own business logic.
- Actions should remain reusable outside the HTTP layer.
- Resources own outward API payload shape.
- Use DTOs for grouped payloads with multiple related scalar or array fields, not for single models or otherwise obvious arguments.
- Filtering and sorting rules should be sourced from model query traits when the endpoint uses Spatie Query Builder.

## Completion Checklist

- Route paths and middleware are correct.
- Validation and authorization live in the request class.
- Controller logic stays thin.
- Action logic is isolated and reusable.
- Resource classes shape the API response.
- Filtering, sorting, and bulk collection behavior follow repository conventions when present.
- Feature tests cover the changed contract.
