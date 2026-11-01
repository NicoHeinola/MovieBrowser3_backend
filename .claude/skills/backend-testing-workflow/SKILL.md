---
name: backend-testing-workflow
description: Write or update Pest tests for this Laravel API. Use when adding regression coverage for endpoints, auth, validation, actions, jobs or video-file flows, or when a change raises behavioral risk.
---

# Backend Testing Workflow

## Workflow

1. Name the riskiest behavior the change introduced: contract, auth boundary, validation, persistence, file side effects or rollback.
2. Build the smallest realistic state with factories, `actingAsAdmin()` or a user token, and `putVideoFile()` for files.
3. Prefer a feature test in the matching `tests/Feature/XApiTest.php`. Use a unit test only for logic cheaper to test without HTTP.
4. Assert the outward result first (status, JSON paths), then the persisted rows or files that explain the risk.
5. Add the unhappy paths the change crosses: 401, 403, 404, 422, and rollback when files move or are deleted.
6. Fake external effects instead of running them: `Queue::fake()`, `Http::fake()`, `Process::fake()`, `Sleep::fake()`, or mock the action.
7. Run the file or `--filter` first, then the full suite if shared factories, models or helpers changed.

## Done when

- The riskiest behavior has a direct assertion, and both success and failure branches are covered.
- Tests pass, and any coverage gap is stated in the hand-off.
