---
description: "Testing conventions for Pest PHP tests under tests. Use when creating or editing repository test files."
applyTo: "tests/**/*.php"
---

# Testing Rules

- Use Pest for repository tests.
- Feature tests should cover API contracts, auth behavior, validation outcomes, and persistence side effects.
- Unit tests should focus on pure or narrowly scoped logic that does not need the full HTTP stack.
- Reuse existing test patterns and prefer assertions on user-visible behavior over implementation details.
- Run the narrowest useful test target first and call out any testing gap when behavior changed without matching coverage.
