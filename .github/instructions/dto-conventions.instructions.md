---
description: "DTO conventions for Spatie Laravel Data classes. Use when creating or editing files under app/Dtos."
applyTo: "app/Dtos/**/*.php"
---

# DTO Rules

- Use declared constructor-promoted properties instead of class-level `@property` docblocks.
- Prefer camelCase DTO property names and rely on the repository Spatie Data input mapping for snake_case payloads unless a DTO needs a narrower override.
- Use DTOs for grouped scalar or array payloads at action boundaries, not for single models or a small number of obvious arguments.
- Keep DTO classes declarative; use `Optional` unions only when the payload genuinely supports partial input.
- Define nested DTO arrays explicitly with `DataCollectionOf` and accurate array annotations so hydration stays unambiguous.
