---
description: "Model organization rules for Laravel models. Use when creating or editing files under app/Models for model classes, relations traits, or query traits."
applyTo: "app/Models/**/*.php"
---

# Model Organization Rules

## Structure

- Place each model in its own namespace folder under `app/Models/<Domain>/`.
- Keep the base model in the folder root, for example `app/Models/Show/Show.php`.
- Keep supporting traits in the same model folder when practical, for example `app/Models/Show/HasShowRelations.php` and `app/Models/Show/HasShowQuery.php`.
- Nested subfolders for traits are optional, not required.

## Responsibilities

- Base model classes should compose traits, define casts, fillable behavior, and factory resolution.
- Relations traits should contain Eloquent relationship methods only.
- Query traits should expose repository conventions such as `getAllowedFilters()` and `getAllowedSorts()` for Spatie Query Builder.

## Boundaries

- Do not move controller or request logic into models or traits.
- Do not parse request query parameters inside model code.
- Keep query traits focused on reusable model query configuration rather than endpoint-specific response shaping.
