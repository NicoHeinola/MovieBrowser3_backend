---
description: "Resource conventions for Laravel API resources. Use when creating or editing files under app/Http/Resources."
applyTo: "app/Http/Resources/**/*.php"
---

# Resource Rules

- Route controller-managed API payloads through resource classes rather than returning raw JSON arrays from controllers.
- Keep top-level API contract keys and status codes stable unless the task explicitly changes them.
- Use focused resource classes for outward payload shape and compose nested resources when embedding models, DTOs, or collections.
- Avoid redundant wrapper resources that only nest another resource under a single key unless the API contract explicitly needs that envelope.
- Keep success payloads lean and avoid generic `message` fields unless the API contract explicitly needs them.
- Keep resources presentation-focused; do not move validation, persistence, or action logic into them.
- Keep top-level resources unwrapped by default and only introduce an envelope when the API contract explicitly needs one.
