---
description: "Model folder conventions for Laravel models and their local traits. Use when creating or editing files under app/Models."
applyTo: "app/Models/**/*.php"
---

# Model Organization Rules

- Place each model in its own namespace folder under `app/Models/<Domain>/`.
- Keep the base model at the folder root.
- Put relationship traits under `Relations/` and query-builder traits under `Query/` within that model namespace.
- Base models should compose those traits and own casts, fillable behavior, and factory wiring.
- Relations traits should contain Eloquent relationship methods only.
- Query traits should contain reusable Eloquent scope methods and expose reusable Spatie Query Builder filters, sorts, and query configuration rather than parse request input or shape API responses.
- Prefer `AllowedFilter::custom` with `MetadataFilter` over built-in filter types like `partial` or `exact`. `MetadataFilter` supports operator prefixes (`like:`, `eq:`, `null`, etc.) and relation properties via dot notation (e.g., `titles.title`), keeping filter behavior consistent across all columns.
- Scope handles (e.g., `scopeSearch`) should be placed in the query trait to consolidate all query-related logic.
