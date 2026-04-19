---
name: backend-endpoint-workflow
description: "Build or refactor a Laravel API endpoint with the project-standard request-controller-action flow. Use when adding routes, request validation, thin controllers, action logic, auth-protected endpoints, or feature tests for API behavior."
---

# Backend Endpoint Workflow

Use this skill when a task adds or refactors one API endpoint or a small related slice. Use the matching instruction files for route, request, controller, action, DTO, model, and resource structure while this skill sequences the work.

## Workflow

1. Confirm the route shape, middleware, and expected API contract.
2. Add or update the request class for validation; keep authorization in route middleware and policy checks at the controller boundary, and prefer explicit rules or custom rule objects before request lifecycle hooks.
3. Keep the controller thin, extract validated data there, and introduce a DTO only when the endpoint should follow the DTO conventions.
4. Keep application logic inside the action and keep its return value HTTP-agnostic.
5. Return controller-managed API payloads through resources so outward response shaping stays in the resource layer, and keep success payloads lean unless the contract needs extra metadata.
6. For index-style endpoints, reuse model query traits for Spatie Query Builder configuration and explicit eager loading rather than ad hoc query parsing in the controller.
7. Use dedicated collection requests and actions for bulk operations.
8. Add or update feature tests for the changed contract.
9. If the change standardizes or changes a durable repository pattern, update the owning instruction or skill file in the same change set.
10. Run the narrowest relevant Pest command.

## Completion Checklist

- Route paths and middleware are correct.
- Validation lives in the request class, and authorization is enforced through middleware or controller policy checks.
- Controller logic stays thin.
- Action logic is isolated and reusable.
- Resource classes shape the API response.
- Filtering, sorting, and bulk collection behavior follow repository conventions when present.
- Relevant instruction or skill files were reviewed and updated when the work changed a durable convention.
- Feature tests cover the changed contract.
