# MovieBrowser3 Backend Copilot Instructions

## Repository Defaults

- This repository is a Laravel 13 JSON API backend for movie-library data.
- Prefer extending existing backend namespaces and patterns before creating new top-level structure.
- Preserve existing API response fields and status codes unless the task explicitly changes the contract.
- Keep auth and token behavior aligned with Sanctum.

## Architecture Defaults

- Follow the request -> controller -> action split for HTTP work.
- Requests own endpoint validation and authorization.
- Controllers orchestrate HTTP flow.
- Actions hold reusable application logic.
- Models own persistence concerns such as relationships, casts, and reusable query configuration.
- Resources shape outward API payloads.

## Validation Defaults

- Use Composer, Artisan, and Pest for project commands and validation.
- Add or update the narrowest relevant tests when behavior changes.
