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
4. Keep application logic inside the action.
5. Add or update feature tests for the endpoint behavior.
6. Run the narrowest relevant Pest command.

## Boundary Checks

- Routes register endpoints, they do not hold endpoint logic.
- Request classes own input validation, not actions.
- Controllers orchestrate HTTP flow, they do not own business logic.
- Actions should remain reusable outside the HTTP layer.

## Completion Checklist

- Route paths and middleware are correct.
- Validation and normalization live in the request class.
- Controller logic stays thin.
- Action logic is isolated and reusable.
- Feature tests cover the changed contract.
