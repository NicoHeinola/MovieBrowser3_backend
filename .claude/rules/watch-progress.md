---
description: Per-user watch status, watch progress and the live progress SSE stream. Use when editing watch status or watch progress models, actions, controllers, resources, config or tests.
paths:
  - 'app/**/ShowEntryWatchStatus/**'
  - 'app/**/ShowWatchProgress/**'
  - 'app/Http/Controllers/ShowEntryWatchStatusController.php'
  - 'app/Http/Controllers/ShowWatchProgressStreamController.php'
  - 'config/watch_progress.php'
  - 'tests/Feature/ShowEntryWatchStatusApiTest.php'
  - 'tests/Feature/ShowWatchProgressStreamApiTest.php'
applyTo: 'app/**/ShowEntryWatchStatus/**,app/**/ShowWatchProgress/**,app/Http/Controllers/ShowEntryWatchStatusController.php,app/Http/Controllers/ShowWatchProgressStreamController.php,config/watch_progress.php,tests/Feature/ShowEntryWatchStatusApiTest.php,tests/Feature/ShowWatchProgressStreamApiTest.php'
---

# Watch Status and Progress

## Watch Status

- Status is per user and per show entry (`WatchStatus` enum, `ShowEntryWatchStatus`), so season 1 can be `completed` while season 2 is `watching`. Any authenticated user can `PUT|DELETE show-entries/{show_entry}/watch-status`.
- `ShowController` and `ShowEntryController` eager-load only the current user's row of `watchStatuses`; `ShowEntryResource` nests the first one as `watch_status` (object or `null`). `filter[watch_status]` uses the `withWatchStatus` scope.
- On shows, `filter[watch_status]` and `filter[exclude_watch_status]` match the user's combined status, which must mirror the frontend's `getCombinedWatchStatus`: the shared status when every entry has it, `mixed` when statuses differ or only some entries have one, and `null` otherwise. `mixed` is a filter value only, not a `WatchStatus` case.
- `filter[entry_watch_status]` and `filter[exclude_entry_watch_status]` match any single entry instead, and don't know `mixed`.

## Watch Progress

- `show_watch_progress` holds one row per user and show: entry plus nullable episode (nulled when the episode is deleted), and nullable `position_seconds`/`duration_seconds` inside that episode. `ShowResource` exposes the current user's row as `watch_progress` (object or `null`). "Continue watching" is `filter[has_watch_progress]=1&sort=-last_watched_at`.

## Live Stream

- `GET watch-progress/stream` is a server-sent events stream. `StreamShowWatchProgressAction` polls the user's rows every `watch_progress.stream_poll_seconds` (default 2, minimum 1), so writes from any source reach it without events.
- It sends only rows changed after the stream opened, deduplicated per show by entry, episode, position and `updated_at` (whole seconds), and ends when the token is deleted or after `stream_lifetime_seconds` (default 3600, minimum 60).
- The controller writes `retry: 3000`, then `event: watch-progress` / `id: <updated_at>` / `data: <ShowWatchProgressResource>`, and `: ping` after `stream_heartbeat_seconds` (default 25, minimum 1) without output. Each open stream holds one PHP server worker.
- Tests use `Sleep::fake(syncWithCarbon: true)` with `Sleep::whenFakingSleep(...)` to write progress between polls and read `streamedContent()`.
