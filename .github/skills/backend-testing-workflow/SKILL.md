---
name: backend-testing-workflow
description: "Write or update Pest tests for this Laravel API repository. Use when adding regression coverage for endpoints, auth flows, validation rules, actions, or domain logic changed by a feature."
---

# Backend Testing Workflow

Use this skill when testing is part of the task or when the change increases behavioral risk.

## Workflow

1. Identify the highest-risk behavior introduced or changed by the work.
2. Set up the smallest realistic factory state, auth state, and seed data needed to exercise that risk.
3. Prefer feature tests for endpoint contracts, auth flow, validation outcomes, and persistence side effects.
4. Add unit tests only when logic is clearer or cheaper to validate outside the full HTTP stack.
5. Assert user-visible behavior first, then add persistence or branching assertions that explain the risk being covered.
6. Add success, failure, and authorization or validation cases when the changed behavior crosses one of those boundaries.
7. Reuse existing repository testing style before introducing new helpers or patterns.
8. Run the narrowest useful Pest target first, then widen if shared factories, seed data, or cross-endpoint behavior changed.

## Repository-Specific Focus

- Auth and protected endpoints should keep explicit coverage.
- Validation changes should be reflected in request-level or feature-level assertions.
- Action logic with meaningful branching should be covered at the most useful layer.

## Common Risks

- Asserting implementation details instead of the outward contract or persisted result.
- Missing authorization or guest coverage when an endpoint changes middleware or policy behavior.
- Relying on stale factories or seed data that no longer match the changed schema.
- Skipping unhappy-path assertions for validation, missing models, or rejected operations.

## Completion Checklist

- The highest-risk behavior has direct test coverage.
- Test setup matches the smallest realistic state needed for the behavior.
- Success and failure paths are both covered when the change can branch.
- Auth and validation assertions were updated when those rules changed.
- The narrowest relevant Pest target was run, and any remaining gap is called out.

## Useful Commands

- `./vendor/bin/pest tests/Feature/...`
- `./vendor/bin/pest tests/Unit/...`
- `composer test`
