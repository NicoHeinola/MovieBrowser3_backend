---
description: Routing conventions for the API routes file. Use when editing routes/api.php or wiring backend HTTP endpoints.
paths:
  - 'routes/api.php'
applyTo: 'routes/api.php'
---

# Routing Rules

- Keep routes declarative: prefixes, middleware, controller mappings and route names. No validation or logic in closures.
- Everything sits under `v1`. Read routes (`index`, `show`) go in the `auth:sanctum` group, write routes in the `['auth:sanctum', 'admin']` group, with the same `apiResource` registered twice.
- Register resources with `apiResource` or `apiSingleton` (one per parent), not hand-written `Route::put/delete` lines. Plain `Route::post/delete` stays only for existing non-CRUD cases such as `shows.categories`.
- Child resources are shallow; multi-word URIs are kebab-case.

```php
// inside Route::middleware('auth:sanctum')
Route::apiResource('show-sections', ShowSectionController::class)->only(['index', 'show']);
Route::apiResource('shows.links', ShowLinkController::class)->shallow()->only(['index', 'show']);
Route::apiSingleton('show-entries.watch-status', ShowEntryWatchStatusController::class)->only(['update', 'destroy'])->destroyable();

// inside Route::middleware(['auth:sanctum', 'admin'])
Route::apiResource('show-sections', ShowSectionController::class)->except(['index', 'show']);
Route::apiResource('shows.links', ShowLinkController::class)->shallow()->except(['index', 'show']);
```
