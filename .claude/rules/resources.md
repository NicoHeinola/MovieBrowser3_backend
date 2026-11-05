---
description: Resource conventions for Laravel API resources. Use when creating or editing files under app/Http/Resources.
paths:
  - 'app/Http/Resources/**/*.php'
applyTo: 'app/Http/Resources/**/*.php'
---

# Resource Rules

- `JsonResource::withoutWrapping()` is global: single resources are unwrapped, paginated collections have `data`. Don't add envelopes or wrapper resources unless the contract needs one.
- Add a `/** @mixin Model */` docblock and nest relations through `whenLoaded` with their own resource classes.
- Keep resources presentation-only and keep existing keys stable.
- User-facing text goes out as a code with string `params` (an enum in `app/Enums`), and the frontend translates it. Cast `params` to an object so empty encodes as `{}`.
- Never touch the disk for a per-episode file check: videos can sit on slow Windows drives. Read the stored `has_video_file`, which `CheckEpisodeVideoFilesJob` refreshes.
- A field derived by application logic calls the owning action, e.g. `'file_path' => ResolveEpisodeVideoPathAction::run($this->resource)`, never a model accessor.
