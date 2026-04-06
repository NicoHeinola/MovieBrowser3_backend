---
description: "Testing rules for Pest feature and unit tests. Use when creating or editing PHP tests under tests/Feature or tests/Unit for API, auth, validation, or domain behavior."
applyTo: |
  tests/Feature/**/*.php
  tests/Unit/**/*.php
---

# Testing Rules

## Framework

- Use Pest for repository tests.
- Feature tests should cover HTTP contracts, auth behavior, validation outcomes, and persistence side effects.
- Unit tests should focus on pure or narrowly scoped logic that does not need the full HTTP stack.

## Coverage Expectations

- Add or update feature tests when endpoints, validation rules, auth flow, or response shapes change.
- Reuse existing test patterns before introducing a new style.
- Prefer asserting user-visible API behavior over implementation details.

## Execution

- Run the narrowest useful test target first.
- Good defaults are `./vendor/bin/pest tests/Feature/...`, `./vendor/bin/pest tests/Unit/...`, and `composer test`.
- Report any validation gap if behavior changed but full coverage was not added.