---
name: crud-resource
description: Scaffold or extend an API resource or endpoint in this Laravel backend (migration, model + Query/Relations traits, factory, DTOs, actions, form requests, resource, controller, routes, Pest feature test). Use when adding a new entity or endpoint set like categories, show-sections or a nested child of shows, or when adding or refactoring a single endpoint on an existing resource.
---

# CRUD Resource

Build or extend a vertical slice for a domain `X` (StudlyCase `ShowSection`, table `show_sections`, URI `show-sections`). The rules for each layer load when you open its files; this skill gives the order. Read the closest existing slice first and copy its shape:

- Top-level resource: `Category` (simplest) or `ShowSection`
- Shallow child of `shows`: `ShowLink`, `ShowTitle` or `ShowEntry`
- Enum column: `ShowLink` (`app/Enums/ShowLinkType.php`)
- One-per-parent singleton: `ShowEntryWatchStatus`
- Many-to-many with sync: `Website` (`tag_ids`)

## Workflow

1. **Contract**: decide routes, read/write groups, payload fields and status codes. Skip steps below that the change does not touch.
2. **Migration**: one per table, parent first.
3. **Model**: `X.php` plus `Query/` and `Relations/` traits. Add the inverse relation on the related model.
4. **Factory** in `database/factories` (related models as `Related::factory()`). Add a seeder to `DatabaseSeeder` only if local data needs it.
5. **DTOs**: `CreateXData`, `UpdateXData`.
6. **Actions**: `{Create,Update,Delete}XAction`, each in `DB::transaction`.
7. **Requests**: `StoreXRequest`, `UpdateXRequest`.
8. **Resource**: `XResource`; embed it in a parent resource with `whenLoaded` if needed.
9. **Controller**: thin, eager-loading what the resource embeds.
10. **Routes**: the reads `apiResource` and the writes `apiResource` in their groups.
11. **Tests**: `tests/Feature/XApiTest.php`, following the testing rule's coverage list.
12. Run `composer lint:fix`, `php artisan test --compact --filter=XApiTest`, then the full suite if shared models, factories or `ActionTransactionTest` could be affected.

## Done when

- Every layer the change touches follows its rule, and the inverse relation and factory are wired.
- Pint is clean, and the feature test and the full suite pass.
- A new durable pattern is recorded in the owning rule (or `AGENTS.md`) in the same change.
