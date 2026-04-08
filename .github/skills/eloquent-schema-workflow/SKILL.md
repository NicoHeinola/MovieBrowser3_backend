---
name: eloquent-schema-workflow
description: "Design or refactor Laravel models, relationships, migrations, factories, and seeders for backend entities. Use when adding movie-library data models, changing schema, wiring Eloquent relations, or shaping persistence for new features."
---

# Eloquent And Schema Workflow

Use this skill when the work changes persistence or domain structure rather than only HTTP wiring.

## Workflow

1. Identify the entity and relationship changes required.
2. Add or update migrations with explicit keys, constraints, and nullability.
3. Keep model responsibilities to relationships, casts, scopes, and model-level concerns.
4. Place each model in its own namespace folder under `app/Models/<Domain>/` and keep reusable logic in focused query and relations traits next to the model when practical.
5. Put relationship methods in the relations trait and Spatie Query Builder configuration such as `getAllowedFilters()` and `getAllowedSorts()` in the query trait.
6. Add or update factories and seeders when test or local data needs change.
7. Recheck queries for eager-loading and integrity risks.
8. Add or update tests that cover the changed persistence behavior.

## Common Risks

- Missing foreign-key constraints or cascade decisions.
- Implicit N+1 queries after adding relations.
- Mixing persistence rules into controllers instead of models or actions.
- Putting relation methods, query-builder configuration, and model behavior into one large class instead of the repository's folder-based model layout.
- Schema changes without matching factories or seed data updates.

## Completion Checklist

- Migration intent is explicit.
- Relationship ownership is clear.
- Model folder layout is consistent and supporting traits live alongside the model or in a clearly local structure within the same model namespace.
- Factories and seeders still support tests and local development.
- Query behavior is acceptable for the changed endpoints.
