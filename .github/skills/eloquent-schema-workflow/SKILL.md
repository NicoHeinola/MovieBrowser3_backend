---
name: eloquent-schema-workflow
description: "Design or refactor Laravel models, relationships, migrations, factories, and seeders for backend entities. Use when adding movie-library data models, changing schema, wiring Eloquent relations, or shaping persistence for new features."
---

# Eloquent And Schema Workflow

Use this skill when the work changes persistence or domain structure rather than only HTTP wiring. Use the matching model instruction files for file placement and trait structure while this skill sequences the persistence work.

## Workflow

1. Identify the entity and relationship changes required.
2. Add or update migrations with explicit keys, constraints, and nullability.
3. Keep model responsibilities to relationships, casts, scopes, and model-level concerns.
4. Follow the repository model folder layout and split local relationship and query concerns into the model traits defined by the model instructions.
5. Keep relationship methods in relation traits and keep Spatie Query Builder configuration such as `getAllowedFilters()` and `getAllowedSorts()` in query traits.
6. Add or update factories and seeders when test or local data needs change.
7. Recheck queries for eager-loading, integrity, and backfill risks.
8. Add or update tests that cover the changed persistence behavior.

## Common Risks

- Missing foreign-key constraints or cascade decisions.
- Implicit N+1 queries after adding relations or changing eager-loading assumptions.
- Schema changes without matching factories, seed data, or test updates.
- Destructive or non-nullable changes without a safe migration or backfill path.

## Completion Checklist

- Migration intent is explicit.
- Relationship ownership is clear.
- Model instructions still fit the changed files and traits.
- Factories and seeders still support tests and local development.
- Query behavior is acceptable for the changed endpoints.
