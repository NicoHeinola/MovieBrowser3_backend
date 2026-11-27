---
description: Action conventions for Laravel actions. Use when editing files under app/Actions or moving application logic out of controllers.
paths:
  - 'app/Actions/**/*.php'
applyTo: 'app/Actions/**/*.php'
---

# Action Rules

- One use case per action. A row's own changes use CRUD verbs (`Create`, `Update`, `Delete`; not `Set` or `Clear`); anything else says what it does in words the app already uses (`PlayEpisodeAction`, `ReplaceEpisodeVideoAction`, `RetryDownloadAction`; see the words in `AGENTS.md`). `Is…Action` answers yes or no, `Check…Action` finds out and reports or records it, `Resolve…Action` looks a value up. `Start…Action` records a background run and queues its job (`StartVideoBaseFolderMoveAction`, `StartVideoFileCheckAction`); the job ends it. `tests/Unit/ArchitectureTest.php` checks the rest of this file's checkable rules: the `*Action` suffix and `AsAction` trait, no request objects, no SPL iterators, unreported "already running" exceptions.
- Explicit `handle()` signatures: models and scalars directly, a DTO for grouped payloads or values that always travel together (see the DTO rules). A child create takes the parent first: `handle(Show $show, CreateXData $data)`.
- An action does what its name says, every time. The caller decides whether to call it (`if ($isLastEpisode) …`), or the action's name says what it depends on. It doesn't check for a state that its write would leave the same anyway: Eloquent's `update` and `updateOrCreate` write nothing when nothing changed, and a loop over nothing does nothing.
- Return models, DTOs or simple values, never HTTP payloads. Actions must work from controllers, jobs, commands or tests.
- Create uses `X::query()->create([...explicit snake_case keys...])`; delete calls `$x->delete()`. Partial updates fill through the fillable allowlist and return `fresh()`:

```php
$data->x->fill(Arr::only($data->all(), $data->x->getFillable()))->save();

return $data->x->fresh();
```

## Transactions

- An action that writes more than one row, or a row plus a file or an outside system, wraps that in `DB::transaction`. A single write needs none; older actions still have one, so drop it when you touch them. `tests/Unit/ActionTransactionTest.php` finds actions that write twice without one.
- Only what must succeed or fail together goes inside. Reads and checks go before it, unless they lock the row they check (`lockForUpdate`): without a lock a transaction doesn't keep the row from changing between the check and the write. A unique index with `createOrFirst` decides a race better than a check.
- Don't hold one open during slow work (a copy, VLC's launch, a delay, reading a drive): do that first, then write what came of it. Writes that stand on their own, one per download or per episode with slow work in between, get none. The one exception is an upload in `CreateEpisodeAction` and `UpdateEpisodeAction`: the file is copied and read inside their transaction, so a failed upload undoes the other changes too. It locks only that episode.
- Inside the transaction, write rows first and touch the filesystem last, so a failed file move rolls the rows back.
- Defer irreversible side effects with `DB::afterCommit`. Actions that delete rows owning video files resolve the paths through `DeleteEpisodeVideosAction` inside the transaction; it defers the removal so a rollback keeps the files, and stops downloading the torrent files picked for those episodes (`StopDownloadsOfDeletedEpisodesAction`).

## Guards

- Each precondition is checked in one place: the action that must not run without it, when the action has other callers (jobs, the scheduler, commands) or a unique index decides; otherwise the request or middleware. Never check it in the request, the middleware and the action alike.
- An error the frontend shows extends `ApiException` (code, status, params), which renders it. A blocking state (something else is running) throws a `*RunningException` with a 409; callers that can't show it, like the scheduler, simply let it end the run.

## Folders

- A domain folder holds its use cases. Once helpers crowd them out, those go in subfolders by concern: `Download/Client` (qBittorrent: connection, sync, which files it downloads), `Download/Import` (finished files into episodes), `Episode/VideoFile/Path` (where a video belongs, and whether the video base folder is available).
- The video files have one check (`VideoFileCheck`): `CheckEpisodeVideoFileAction` records one episode's file, size and length, and everything else only picks the episodes: the daily background check (`CheckEpisodeVideoFilesJob`), and one show while the admin waits (`CheckShowVideoFilesAction`). Anything else to find out about a video goes in that action, not in a run of its own. `VideoBaseFolderMove` stays apart: it moves files and turns around when that fails.
- Work that only needs a path (copying, linking, cleaning up folders, disk space through `EnsureDiskSpaceAction`) goes in `File`, which knows no episodes, settings or video base folder. `Episode/VideoFile` is for what does: what a video is, where it belongs, and the folder it lives in.
- A helper with one caller and a few lines stays a private method of that caller.
- No optimization before it's shown to be needed: no cache, memoized value, reused connection, chunking or hand-written parser because it could be slow. Measure first, and write the measurement next to the optimization.

## Helpers

- Logic that must not live on models (paths, setting lookups) goes in read-only helper actions, e.g. `ResolveEpisodeVideoPathAction::run($episode)`.
- `RenameVideoFileAction` leaves a file alone whose path didn't change, so its callers need not compare names first.
- `video_base_folder` changes only through a video base folder move. `StartVideoBaseFolderMoveAction` records it and always queues `MoveVideoBaseFolderJob`. Each job calls `ContinueVideoBaseFolderMoveAction`: it moves one episode's file outside any transaction (`MoveEpisodeVideoToTargetFolderAction`, safe to repeat), in the direction the model gives (`getSourceFolder()`, `getTargetFolder()`). With no file left, or when both folders are the same, it calls `FinishVideoBaseFolderMoveAction`, which saves the setting. Only the job finishes a move. On failure the job moves everything back. Never move it inside a request or a transaction.
- Downloads import only through `SyncDownloadsJob` (scheduled every minute), which queues one `ImportDownloadPickJob` per finished file on the `imports` queue, which has its own worker: copying can take minutes, so never in a request and never on the default queue. Both are safe to repeat and to kill. The copy runs outside a transaction, next to where it goes: it takes minutes, and renaming or deleting its episode must not wait for it. The import then reads everything again, locks the episode before its pick (as a delete does) and puts the copy in place. Run exactly one `imports` worker. The sync moves a download's status forward (`SyncUnfinishedDownloadsAction`) and clears finished torrents out of qBittorrent (`RemoveSeededTorrentsAction`); `RetryDownloadAction` starts it over from the files that are really there. An episode has at most one pick: picking a file for it in one download takes it out of the other.
- Copy a video only with `CopyFileAction`: it links, has the bridge copy, or copies by itself, whichever is fastest. A link is the same data as the original, so nothing may change a video in place.
- A new file becomes an episode's video only through `ReplaceEpisodeVideoAction`, for uploads and imports alike: put it complete on the video drive under a name of its own (`.uploading`, `.importing`), and the action reads its length, saves the row and renames it into place last. A length that couldn't be read is tried again by the next check.
- An unmounted drive makes every file look missing, and a file written then goes into the container's empty folder. So confirm `IsVideoBaseFolderAvailableAction` before acting on a missing file or writing a new one (`StoreUploadedVideoAction` refuses with `VideoBaseFolderUnavailableException`). The video file check is the exception: only a video base folder that doesn't exist or can't be read stops it. An empty one is a normal result, because the next run repairs its flags.
- List folders with `File::files()` / `File::directories()` or `scandir`: SPL iterators silently skip entries on a Windows drive under WSL (drvfs, where production keeps its videos), so a folder can look empty. Delete an empty folder with `DeleteEmptyDirectoriesAction` (`rmdir`, which refuses one that isn't), never with `File::deleteDirectory`.
- Video paths must stay valid on Windows, because a Windows `vlc.exe` opens them: `ResolveEpisodeVideoPathAction` replaces `\ / : * ? " < > |` and strips trailing dots and spaces. Changing those rules changes every computed path, so existing files need moving too, and never from a migration.
