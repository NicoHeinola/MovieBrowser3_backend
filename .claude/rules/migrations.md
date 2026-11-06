---
description: Migration conventions. Use when creating or editing files under database/migrations.
paths:
  - 'database/migrations/**/*.php'
applyTo: 'database/migrations/**/*.php'
---

# Migration Rules

- One migration creates or changes exactly one thing: one table, or one alter. A feature with several tables gets several migrations with ascending timestamps, parent table first.
- Timestamps sort after the latest existing migration. Use anonymous classes.
- Use `foreignId(...)->constrained()->cascadeOnDelete()` (or an explicit `nullOnDelete()`), explicit `nullable()`, and unique indexes where the data requires them (case-insensitive names use a `lower(name)` unique index).
- Migrations change the schema and seed rows only. They never run app logic: no `App\` classes (actions, models, jobs) and no filesystem work, because that code keeps changing while the migration stays.
