---
description: Action conventions for Laravel actions. Use when editing files under app/Actions or moving application logic out of controllers.
paths:
  - 'app/Actions/**/*.php'
applyTo: 'app/Actions/**/*.php'
---

# Action Rules

- One use case per action, with CRUD verbs (`Create`, `Update`, `Delete`; not `Set` or `Clear`). `tests/Unit/ArchitectureTest.php` checks the rest of this file's checkable rules: the `*Action` suffix and `AsAction` trait, no request objects, no SPL iterators, unreported "already running" exceptions.
- Explicit `handle()` signatures: models and scalars directly, a DTO for grouped payloads or values that always travel together (see the DTO rules). A child create takes the parent first: `handle(Show $show, CreateXData $data)`.
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
- Defer irreversible side effects with `DB::afterCommit`. Actions that delete rows owning video files resolve the paths through `DeleteEpisodeVideosAction` inside the transaction; it defers the removal so a rollback keeps the files, and stops downloading the torrent files picked for those episodes (`ReleaseEpisodeDownloadsAction`).
- Exempt (write no rows of their own, run inside a caller's transaction, or must not hold one open):
  - `Episode/VideoFile/*`, `Relocate*VideosAction`, `DeleteEpisodeVideosAction`
  - `EpisodePlayback/OpenVlcPlaylistAction`, `EpisodePlayback/CheckVlcBinaryAction`
  - `Backup/CreateBackupAction`, `Backup/CreateScheduledBackupAction`, `Backup/DeleteOldBackupsAction`: dump and prune files only, and `backups:create` runs them before the migrations
  - `CreateEpisodePlaybackAction`: opens VLC before its transaction so the slow launch holds no lock and a failed launch saves no progress
  - `LoginUserAction`: its anti-brute-force delay must not hold a transaction; the token write goes through `IssueAuthTokenAction`
  - read-only `VideoRelocation/GetVideoRelocationAction`, `VideoDurationScan/GetVideoDurationScanAction`, `ShowWatchProgress/StreamShowWatchProgressAction` (polls for up to an hour) and the change lists it polls (`ShowWatchProgress/ListChangedShowWatchProgressAction`, `ShowEntryWatchStatus/ListChangedShowEntryWatchStatusesAction`), `AppVersion/*` (GitHub release check, cache only), `Dashboard/GetDashboardStatsAction`, `NekoBt/ListNekoBtTorrentsAction` (torrent search, cache only), `ShowTemplate/*` (outside show pages), `EpisodePlayback/ResolveEphemeralPortRangeAction`, `Setup/GetSetupStatusAction`, `Setup/CheckSetupSettingsAction`, `Setup/CheckMediaFolderPathAction`, `Download/CheckQBittorrentConnectionAction`, `Download/GetDownloadDetailsAction` (asks qBittorrent), `Download/Resolve*Action`, `Backup/ListBackupFilesAction`, `LegacyImport/CheckLegacyShowImportAction` and `LegacyImport/ResolveLegacyEpisodeSourcePathAction`

## Guards

- Each precondition is checked in one place: the action that must not run without it, when the action has other callers (jobs, the scheduler, commands) or a unique index decides; otherwise the request or middleware. Never check it in the request, the middleware and the action alike.
- A blocking state (something else is running) throws a `*RunningException` that renders its own 409 code; callers that can't show it, like the scheduler, simply let it end the run.

## Helpers

- Logic that must not live on models (paths, setting lookups) goes in read-only helper actions, e.g. `ResolveEpisodeVideoPathAction::run($episode)`.
- `video_base_path` changes only through a video relocation (`CreateVideoRelocationAction`): `RelocateVideoFilesJob` moves one episode's file per job outside any transaction (`RelocateEpisodeVideoAction`, safe to repeat), switches the setting once all moved, and on failure moves everything back. Never relocate inside a request or a transaction.
- `ResolveVideoBasePathAction` memoizes with `once()` from a static method; actions that change that setting call `Once::flush()`. `queue:work` never flushes it, so jobs that resolve video paths flush first.
- Video lengths are read only by the length scan job (`ReadEpisodeVideoDurationsJob`, time-boxed chunks), never in a request or a transaction: a Windows drive under WSL can be slow or gone. Anything that adds or replaces a file through the app queues its episodes with `QueueEpisodeVideoDurationsAction` after commit; `CreateVideoDurationScanAction` only starts a scan for what is already queued. The file check only records: a missing or replaced file loses its length, and the admin reads missing lengths from Settings.
- An unmounted drive makes every file look missing, so confirm `CheckVideoBasePathAction` before acting on a missing file. The video file check is the exception: only a base folder that doesn't exist or can't be read (`CheckVideoBasePathExistsAction`) stops it. An empty one is a normal result, because its stored flags repair themselves on the next run.
- List folders with `File::files()` / `File::directories()` or `scandir`: SPL iterators silently skip entries on a Windows drive under WSL (drvfs, where production keeps its videos), so a folder can look empty and get pruned. `File::deleteDirectory` uses one internally, so call it only on a folder already confirmed empty.
- Video paths must stay valid on Windows, because a Windows `vlc.exe` opens them: `ResolveEpisodeVideoPathAction` replaces `\ / : * ? " < > |` and strips trailing dots and spaces. Changing those rules changes every computed path, so existing files need moving too, and never from a migration.
