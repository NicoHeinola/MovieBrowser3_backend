# MovieBrowser3 Backend Copilot Instructions

## Workspace Defaults

- This repository is a Laravel 13 JSON API backend for movie-library data.
- Use Composer, Artisan, and Pest for project commands and validation.
- Prefer extending existing backend namespaces before creating new top-level folders.
- Treat `routes/api.php` as the entry point for HTTP API work.

## Project Architecture

- For new HTTP features, follow `request -> controller -> action`.
- Request classes own validation, normalization, and authorization.
- Controllers orchestrate HTTP flow and should not contain application logic.
- Actions own application logic and should be reusable outside the HTTP layer.
- Models own relationships, casts, and data access patterns that are truly model concerns.
- Each model lives in its own namespace folder under `app/Models/<Domain>/` with the base model file plus supporting query and relations traits.
- Keep relationship methods in a relations trait and query-builder conventions in a query trait.

## Working Rules

- Preserve existing API response fields and status codes unless the task explicitly changes the contract.
- Keep new API work in backend namespaces such as `app/Http/Requests`, `app/Http/Controllers`, `app/Actions`, `app/Models`, and `tests`.
- Prefer small, single-purpose action classes over accumulating unrelated logic in controllers or models.
- Collection-level mutations such as bulk deletes should use their own request and action rather than overloading single-record actions.
- Avoid passing request objects into actions. Extract validated data in the controller first.
- When querying related data, prefer explicit eager loading over hidden N+1 behavior.
- For filterable or sortable index endpoints, prefer Spatie Query Builder and define allowed filters or sorts on the model query trait so controllers and actions can reuse them.
- Keep auth and token behavior aligned with Sanctum.

## Testing And Validation

- Add or update feature tests when endpoint behavior, auth flow, validation, or response contracts change.
- Add unit tests for pure domain logic when behavior can be validated without the full HTTP stack.
- Run the smallest relevant validation command first, usually `./vendor/bin/pest tests/Feature/...`, `./vendor/bin/pest tests/Unit/...`, or `composer test`.

## Custom Agent Usage

- Use `feature-planner` when a feature spans routes, requests, controllers, actions, models, migrations, or tests.
- Use `feature-researcher` for read-only investigation of ownership, contracts, schema impact, validation flow, or auth behavior before implementation.
- Use `feature-reviewer` for review-style checks focused on regressions, missing validation, broken API contracts, and missing test coverage.

## Customization Guidance

- `.github/copilot-instructions.md` is the canonical workspace-wide guidance file for this repository.
- Keep instructions, skills, and agents concise and discovery-friendly.
- Prefer narrow `applyTo` globs for instructions and concrete trigger phrases in `description` fields.
