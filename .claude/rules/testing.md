---
description: Pest test conventions under tests. Use when creating or editing repository test files.
paths:
  - 'tests/**/*.php'
applyTo: 'tests/**/*.php'
---

# Testing Rules

- Pest function style with `use function Pest\Laravel\{getJson, postJson, ...}`. `RefreshDatabase` applies to `Feature` automatically.
- Feature tests cover API contracts, auth, validation and persistence side effects; unit tests cover narrow logic without the HTTP stack. Assert outward behavior, not implementation details.
- Admin: `actingAsAdmin()`. Regular user: `User::factory()->create()` then `withToken($user->createToken('test-token')->plainTextToken)`.
- A resource usually covers: guest 401 on every endpoint, non-admin write 403, list (`assertJsonCount(n, 'data')`) and unwrapped show (`assertJsonPath('id', ...)`), create (`assertCreated()` + `assertDatabaseHas`), partial update leaves omitted nullable fields intact, delete (`assertNoContent()` + `assertDatabaseMissing`, plus cascades), 422 per meaningful rule via `assertJsonValidationErrors([...])`, and every exposed filter and sort.
- Video files: Feature tests already point `video_base_path` at a throwaway folder cleaned after each test. Seed with `putVideoFile($path)`, assert explicit paths, and cover the rollback case for any flow that moves or deletes files.
