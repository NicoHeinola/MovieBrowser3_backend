---
description: "Routing conventions for the API routes file. Use when editing routes/api.php or wiring backend HTTP endpoints."
applyTo: "routes/api.php"
---

# Routing Rules

- Register API endpoints in this file.
- Keep routes declarative: prefixes, middleware, controller mappings, and route names.
- Preserve the existing versioned and auth-group structure when adding endpoints unless the contract change requires otherwise.
- Do not put validation or business logic in route closures for new work.
- Prefer routing into the request -> controller -> action flow rather than action-as-route shortcuts unless the surrounding code already uses them.
