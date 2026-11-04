---
description: Form Request conventions for API validation. Use when creating or editing files under app/Http/Requests.
paths:
  - 'app/Http/Requests/**/*.php'
applyTo: 'app/Http/Requests/**/*.php'
---

# Request Rules

- `rules()` only, with a `/** @return array<string, list<mixed>> */` docblock. No `authorize()`, no `messages()` overrides: messages never reach the client.
- A 422 is `{ code, params, errors: { field: [{ code, params }] } }`, built by `ValidationErrorResource`; the top-level pair repeats the first error. A new rule (Laravel's or in `App\Rules`) gets a `ValidationErrorCode` case named after it, or it goes out as `invalid`. Errors raised outside a rule (`withMessages`, `after` hooks) use a `ValidationErrorCode` value as the message.
- Name requests with CRUD verbs matching the controller method (`StoreXRequest`, `UpdateXRequest`), even for upsert or "set/clear" semantics.
- No persistence, token creation or side-effecting queries. Use custom rule objects in `App\Rules` for complex validation (e.g. `UniqueIgnoringCase`).
- Update rules start with `'sometimes'`. Read the bound model instead of re-querying: `/** @var X $x */ $x = $this->route('x');` for `Rule::unique(...)->ignore($x->id)`.
- Enums validate with `Rule::enum(XType::class)`.
- List items that must be unique get `distinct:ignore_case` on the `.*` rule in both Store and Update, so duplicates return 422 instead of being merged silently.
- Use `prepareForValidation()` or `withValidator()` only when the endpoint genuinely needs them.
