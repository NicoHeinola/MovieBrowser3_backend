---
paths:
  - 'app/Http/Resources/**/*.php'
---

# Resource Rules

- `JsonResource::withoutWrapping()` is global: single resources are unwrapped, paginated collections have `data`. Don't add envelopes or wrapper resources unless the contract needs one.
- Add a `/** @mixin Model */` docblock and nest relations through `whenLoaded` with their own resource classes.
- Keep resources presentation-only and keep existing keys stable.
- User-facing text goes out as a code with string `params` (an enum in `app/Enums`), and the frontend translates it. Build that `{ code, params }` pair with `ApiErrorDetail::make()`, and error responses in exceptions or middleware with `ApiErrorDetail::response()`: both cast `params` to an object so empty encodes as `{}`.
- Never touch the disk (`tests/Unit/ArchitectureTest.php`): videos can sit on slow Windows drives. For a file check or size, read the stored `has_video_file` and `video_file_size`, which `CheckEpisodeVideoFilesJob` refreshes in the same pass.
- A field derived by application logic calls the owning action, never a model accessor.
