---
name: backend-testing-workflow
description: "Write or update Pest tests for this Laravel API repository. Use when adding regression coverage for endpoints, auth flows, validation rules, actions, or domain logic changed by a feature."
---

# Backend Testing Workflow

Use this skill when testing is part of the task or when the change increases behavioral risk.

## Workflow

1. Identify the highest-risk behavior introduced or changed by the work.
2. Prefer feature tests for endpoint contracts, auth flow, and persistence side effects.
3. Add unit tests only when logic is better validated outside the full HTTP stack.
4. Reuse existing repository testing style before introducing new helpers or patterns.
5. Run the narrowest useful Pest target first, then widen if needed.

## Repository-Specific Focus

- Auth and protected endpoints should keep explicit coverage.
- Validation changes should be reflected in request-level or feature-level assertions.
- Action logic with meaningful branching should be covered at the most useful layer.

## Useful Commands

- `./vendor/bin/pest tests/Feature/...`
- `./vendor/bin/pest tests/Unit/...`
- `composer test`
