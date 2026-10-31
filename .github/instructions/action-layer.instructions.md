---
description: "Action conventions for Laravel actions. Use when editing files under app/Actions or moving application logic out of controllers."
applyTo: "app/Actions/**/*.php"
---

# Action Layer Rules

- Keep each action focused on one application use case.
- Name application actions with the `*Action` suffix so they are visually consistent across domains, including auth-related actions.
- Keep `handle()` signatures explicit: pass models and simple scalars directly, and only introduce a DTO when that boundary should follow the DTO conventions.
- Do not pass request objects into actions or duplicate request validation inside them.
- Return models, DTOs, or simple values rather than controller-shaped HTTP payloads.
- Keep actions reusable from controllers, jobs, commands, listeners, or tests.
- Collection actions may compose single-record actions when that keeps bulk behavior consistent.
- For partial model updates backed by `Optional` DTO fields, prefer passing DTO output into `fill(...)->save()` through the model's fillable allowlist instead of rebuilding attribute arrays field by field.
- Example: if an update DTO maps output names to snake_case, use `$data->episode->fill(Arr::only($data->all(), $data->episode->getFillable()))->save();` and keep only side-effect logic in action body.
- Every action that writes to the database wraps its `handle()` body in `DB::transaction`, even for a single row, so later writes, composed actions, and `DB::afterCommit` callbacks share one boundary. Return through it: `return DB::transaction(function () use ($data): X { ... });`.
- Inside the transaction, do database writes first and filesystem side effects last, so a failed file move rolls the rows back.
- Defer irreversible side effects such as file deletion with `DB::afterCommit`. When an action deletes rows that own video files, resolve the paths through `DeleteEpisodeVideosAction` inside the transaction; it defers the removal so a rollback keeps the files.
- Exceptions: read-only and filesystem helpers (`Episode/VideoFile/*` such as the `Resolve*` path actions, `Relocate*VideosAction`, `DeleteEpisodeVideosAction`) and the VLC helpers `EpisodePlayback/OpenVlcPlaylistAction` and `EpisodePlayback/RequestVlcStatusAction` that write no rows of their own or run inside a caller's transaction, `LoginUserAction`, whose anti-brute-force delay must not hold a transaction open (its token write goes through `IssueAuthTokenAction`), the read-only `ShowWatchProgress/StreamShowWatchProgressAction`, which polls for up to an hour, and `CreateEpisodePlaybackAction`, which opens VLC before its transaction so the slow launch holds no lock and a failed launch saves no progress.
- Read-only helper actions are fine for logic that must not live on models (see the model-organization rules). `ResolveVideoBasePathAction` memoizes with `once()` from a static method so every instance shares the value; actions that change that setting call `Once::flush()`.
