---
description: Action conventions for Laravel actions. Use when editing files under app/Actions or moving application logic out of controllers.
paths:
  - 'app/Actions/**/*.php'
applyTo: 'app/Actions/**/*.php'
---

# Action Rules

- One use case per action, `Lorisleiva\Actions\Concerns\AsAction`, named `*Action` with CRUD verbs (`Create`, `Update`, `Delete`; not `Set` or `Clear`).
- Explicit `handle()` signatures: models and scalars directly, a DTO for grouped payloads or values that always travel together (see the DTO rules). A child create takes the parent first: `handle(Show $show, CreateXData $data)`. Never pass request objects.
- Return models, DTOs or simple values, never HTTP payloads. Actions must work from controllers, jobs, commands or tests.
- Create uses `X::query()->create([...explicit snake_case keys...])`; delete calls `$x->delete()`. Partial updates fill through the fillable allowlist and return `fresh()`:

```php
return DB::transaction(function () use ($data): X {
    $data->x->fill(Arr::only($data->all(), $data->x->getFillable()))->save();

    return $data->x->fresh();
});
```

## Transactions

- Every action that writes to the database wraps its `handle()` body in `DB::transaction`, even for one row, and returns through it.
- Inside the transaction, write rows first and touch the filesystem last, so a failed file move rolls the rows back.
- Defer irreversible side effects with `DB::afterCommit`. Actions that delete rows owning video files resolve the paths through `DeleteEpisodeVideosAction` inside the transaction; it defers the removal so a rollback keeps the files.
- Exempt (write no rows of their own, run inside a caller's transaction, or must not hold one open):
  - `Episode/VideoFile/*`, `Relocate*VideosAction`, `DeleteEpisodeVideosAction`
  - `EpisodePlayback/OpenVlcPlaylistAction`, `EpisodePlayback/RequestVlcStatusAction`, `EpisodePlayback/RequestVlcBridgeAction`, `EpisodePlayback/CheckVlcBinaryAction`
  - `CreateEpisodePlaybackAction`: opens VLC before its transaction so the slow launch holds no lock and a failed launch saves no progress
  - `LoginUserAction`: its anti-brute-force delay must not hold a transaction; the token write goes through `IssueAuthTokenAction`
  - read-only `ShowWatchProgress/StreamShowWatchProgressAction` (polls for up to an hour), `AppVersion/*` (GitHub release check, cache only), `Dashboard/GetDashboardStatsAction`, `EpisodePlayback/ResolveEphemeralPortRangeAction`, `Setup/GetSetupStatusAction`, `Setup/CheckSetupSettingsAction`, `LegacyImport/CheckLegacyShowImportAction` and `LegacyImport/ResolveLegacyEpisodeSourcePathAction`

## Helpers

- Logic that must not live on models (paths, setting lookups) goes in read-only helper actions, e.g. `ResolveEpisodeVideoPathAction::run($episode)`.
- `ResolveVideoBasePathAction` memoizes with `once()` from a static method; actions that change that setting call `Once::flush()`. `queue:work` never flushes it, so jobs that resolve video paths flush first.
- Before treating missing files as real, confirm `CheckVideoBasePathAction`: an unmounted drive makes every file look missing.
- Video paths must stay valid on Windows, because a Windows `vlc.exe` opens them: `ResolveEpisodeVideoPathAction` replaces `\ / : * ? " < > |` and strips trailing dots and spaces. Changing those rules changes every computed path, so existing files need moving too, and never from a migration.
