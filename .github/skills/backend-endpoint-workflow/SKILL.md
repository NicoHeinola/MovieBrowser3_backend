---
name: backend-endpoint-workflow
description: "Build or refactor a Laravel API endpoint with the project-standard request-controller-action flow. Use when adding routes, request validation, thin controllers, action logic, auth-protected endpoints, or feature tests for API behavior."
---

# Backend Endpoint Workflow

Use this skill when a task touches one endpoint or a small endpoint slice rather than a broad repository restructure.

## Workflow

1. Confirm the route and expected API contract.
2. Add or update the request class for validation, normalization, and authorization.
3. Keep the controller thin and extract validated data before calling the action.
4. For collection index endpoints that support filtering or sorting, prefer Spatie Query Builder via model query-trait methods rather than ad hoc query parsing in the controller.
5. Keep application logic inside the action.
6. Use dedicated collection actions and requests for bulk operations such as deleting multiple records.
7. Add or update feature tests for the endpoint behavior.
8. Run the narrowest relevant Pest command.

## Boundary Checks

- Routes register endpoints, they do not hold endpoint logic.
- Request classes own input validation, not actions.
- Controllers orchestrate HTTP flow, they do not own business logic.
- Actions should remain reusable outside the HTTP layer.
- Filtering and sorting rules should be sourced from model query traits when the endpoint uses Spatie Query Builder, regardless of whether those traits sit beside the model or in a local subnamespace.

## Completion Checklist

- Route paths and middleware are correct.
- Validation and normalization live in the request class.
- Controller logic stays thin.
- Action logic is isolated and reusable.
- Filtering, sorting, and bulk collection behavior follow repository conventions when present.
- Feature tests cover the changed contract.
