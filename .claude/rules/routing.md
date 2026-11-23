---
description: Routing conventions for the API routes file. Use when editing routes/api.php or wiring backend HTTP endpoints.
paths:
  - 'routes/api.php'
applyTo: 'routes/api.php'
---

# Routing Rules

- Keep routes declarative: prefixes, middleware, controller mappings and route names. No validation or logic in closures.
- Everything sits under `v1`. Read routes (`index`, `show`) go in the `auth:sanctum` group, write routes in the `['auth:sanctum', 'admin']` group, with the same `apiResource` registered twice. Reads that call an outside system on the admin's behalf (the torrent search, downloads asking qBittorrent) are admin-only, registered once in the admin group.
- Register resources with `apiResource` or `apiSingleton` (one per parent), not hand-written `Route::put/delete` lines. Plain `Route::post/delete` stays only for existing non-CRUD cases such as `shows.categories`.
- Child resources are shallow; multi-word URIs are kebab-case.
- Every route that stores, moves, deletes or plays video files gets `video-files-idle` (whole resource, or `middlewareFor` its methods): it answers 409 `video_relocation_running` while a video folder relocation moves files. Routes whose action already guards that state (starting a file check or a relocation) don't repeat it in middleware.

```php
// inside Route::middleware('auth:sanctum')
Route::apiResource('show-sections', ShowSectionController::class)->only(['index', 'show']);
Route::apiResource('shows.links', ShowLinkController::class)->shallow()->only(['index', 'show']);
Route::apiSingleton('show-entries.watch-status', ShowEntryWatchStatusController::class)->only(['update', 'destroy'])->destroyable();

// inside Route::middleware(['auth:sanctum', 'admin'])
Route::apiResource('show-sections', ShowSectionController::class)->except(['index', 'show']);
Route::apiResource('shows.links', ShowLinkController::class)->shallow()->except(['index', 'show']);
```
