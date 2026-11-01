---
name: eloquent-schema-workflow
description: Change persistence in this Laravel backend - migrations, models, relations, query traits, factories and seeders. Use when adding a column, table, relation, filter or sort, or reshaping data for a feature, without building a whole new resource.
---

# Eloquent and Schema Workflow

Use when the work changes persistence rather than only HTTP wiring. For a whole new resource, use `crud-resource`.

## Workflow

1. List the table, column and relation changes and their delete behavior (cascade, null, restrict).
2. Add one migration per table or alter, with explicit nullability, keys and indexes. Plan a backfill for non-nullable columns on existing tables.
3. Update the model: fillable, casts, relations on both sides, and the query trait's filters, sorts and scopes.
4. Update the factory (and seeder if local data needs it), plus any DTO, request and resource fields the column surfaces through.
5. Check eager loading for new relations in the controllers whose resources embed them.
6. Cover the changed behavior with feature tests (filters, sorts, cascades), then run the affected tests and the full suite.

## Done when

- Migrations run cleanly on a fresh database (`php artisan test` uses in-memory SQLite) and match the Postgres intent.
- Both sides of each relation exist, and no new N+1 query appears in list endpoints.
- Factories still produce valid rows for every test.
