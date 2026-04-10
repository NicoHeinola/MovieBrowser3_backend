---
description: "DTO conventions for Cerbero Laravel DTO classes. Use when creating or editing files under app/Dtos."
applyTo: "app/Dtos/**/*.php"
---

# DTO Rules

- Use class-level `@property` annotations to declare DTO fields; Cerbero DTO reads its mapped properties from the class docblock rather than native class variables.
- Prefer camelCase DTO property names so snake_case or camelCase source keys map cleanly through the package.
- Use DTOs for grouped scalar or array payloads at action boundaries, not for single models or a small number of obvious arguments.
- Keep DTO classes declarative; add flags such as partial support only when the payload genuinely allows missing fields.
- Import nested DTO and model types explicitly so annotated property types remain resolvable.
