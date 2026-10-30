---
description: "Model folder conventions for Laravel models and their local traits. Use when creating or editing files under app/Models."
applyTo: "app/Models/**/*.php"
---

# Model Organization Rules

- Place each model in its own namespace folder under `app/Models/<Domain>/`.
- Keep the base model at the folder root.
- Put relationship traits under `Relations/` and query-builder traits under `Query/` within that model namespace.
- Base models should compose those traits and own casts, fillable behavior, and factory wiring.
- Models represent the database only: fillable, casts, relations, query traits, factory wiring, and accessors or mutators that shape how a column is stored (e.g. `Setting::value()` casting by its `type` column). Anything else, such as building file paths, reading settings for application behavior, memoization, or validation helpers, belongs in an action (or a `App\Rules` rule object for validation).
- Example: an episode's video path is `ResolveEpisodeVideoPathAction::run($episode)`, not `$episode->videoPath()` or a `file_path` accessor.
- Relations traits should contain Eloquent relationship methods only.
- Query traits should contain reusable Eloquent scope methods and expose reusable Spatie Query Builder filters, sorts, and query configuration rather than parse request input or shape API responses.
- Prefer `AllowedFilter::custom` with `MetadataFilter` over built-in filter types like `partial` or `exact`. `MetadataFilter` supports operator prefixes (`like:`, `eq:`, `null`, etc.) and relation properties via dot notation (e.g., `titles.title`), keeping filter behavior consistent across all columns. Filter values are not split on commas (`filter_value_splitting_enabled` is off in `config/query-builder.php`), so pass multiple values as `filter[col][]=a&filter[col][]=b`; each value is applied as an AND condition.
- Scope handles (e.g., `scopeSearch`) should be placed in the query trait to consolidate all query-related logic.
