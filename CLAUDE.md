# MovieBrowser3 Backend

Laravel 13 JSON API (Sanctum bearer auth) for a movie/show library. Postgres in dev (`docker-compose.yml`), in-memory SQLite in tests.

The canonical conventions live in `.github/` (shared with Copilot). They are imported below; keep them as the single source of truth and update them in the same change when a durable convention changes (see "Instruction Maintenance" in the first import).

@.github/copilot-instructions.md
@.github/instructions/routing.instructions.md
@.github/instructions/controller-flow.instructions.md
@.github/instructions/request-validation.instructions.md
@.github/instructions/action-layer.instructions.md
@.github/instructions/dto-conventions.instructions.md
@.github/instructions/model-organization.instructions.md
@.github/instructions/resource-output.instructions.md
@.github/instructions/testing.instructions.md

## Commands

- Tests: `php artisan test --compact` (or `--filter=CategoryApiTest`, or a file path)
- Lint check / fix: `composer lint:check` / `composer lint:fix` (Pint, `laravel` preset; `!$x` without a space after `!`)
- Run `composer lint:fix` and the relevant tests before calling work done.

## Wiring details not obvious from the instructions

- Layout per domain `X`: `app/Actions/X/{Create,Update,Delete}XAction.php`, `app/Dtos/X/{Create,Update}XData.php`, `app/Http/Requests/X/{Store,Update}XRequest.php`, `app/Http/Resources/X/XResource.php`, `app/Models/X/{X.php,Query/HasXQuery.php,Relations/HasXRelations.php}`, `app/Http/Controllers/XController.php`, `database/factories/XFactory.php`, `tests/Feature/XApiTest.php`.
- Actions use `Lorisleiva\Actions\Concerns\AsAction` and are injected into controller methods; call `$action->handle(...)`.
- DTOs are `Spatie\LaravelData\Data`. Global input mapping is snake_case → camelCase (`config/data.php`), so `XData::from($request->validated())` just works.
- Update flow: controller passes `XData::from([...$request->validated(), 'x' => $model])`; the DTO holds the model plus `Type|Optional` fields with `#[MapOutputName(SnakeCaseMapper::class)]`; the action does `fill(Arr::only($data->all(), $model->getFillable()))->save()` and returns `->fresh()`. Nullable fields that can be cleared are `type|Optional|null`. Plain `?type` turns an omitted field into `null` and wipes it on PATCH. Multi-word models use a snake key (`'show_link' => $link`).
- Models only represent the database (no path building, setting lookups or helpers; those are actions such as `ResolveEpisodeVideoPathAction`). Models declare fillable with the `#[Fillable([...])]` attribute, use `HasFactory` with an explicit `newFactory()` (factories live in `Database\Factories`, not next to the model), and use `protected $casts` for enums.
- `JsonResource::withoutWrapping()` is global: single resources are unwrapped; paginated collections have `data`. Resources have a `/** @mixin Model */` docblock and nest relations through `whenLoaded`.
- Index endpoints: `QueryBuilder::for(...)->allowedFilters(...X::getAllowedFilters())->allowedSorts(...)->jsonPaginate()`. Filters use `AllowedFilter::custom('col', new MetadataFilter)` (`App\Models\Show\Query\Filters\MetadataFilter`). Commas in filter values are literal; multiple values use `filter[col][]=...`.
- Per-user watch status lives on show entries (`WatchStatus` enum, `show_entry_watch_statuses` table, `ShowEntryWatchStatus` model, so season 1 can be `completed` while season 2 is `watching`): updated/deleted by any authenticated user via `PUT|DELETE show-entries/{show_entry}/watch-status` (`apiSingleton`). `ShowController` and `ShowEntryController` eager-load only the current user's row of the `watchStatuses` (hasMany) relation, and `ShowEntryResource` nests the first one via `ShowEntryWatchStatusResource` as `watch_status` (object or `null`); `filter[watch_status]=watching` on entry lists uses the `withWatchStatus` scope.
- Playback: `POST episodes/{episode}/playback` (`apiSingleton(...)->creatable()`, any authenticated user) opens VLC first and then upserts the per-user, per-show `ShowWatchProgress` row (`show_watch_progress` table: entry + nullable episode, nulled when the episode is deleted). VLC opens through `OpenVlcPlaylistAction` with every episode of the entry that has a file, starting at the chosen one. VLC has no start-item flag, so the action launches it detached (`nohup` via `Process`) with an RC interface on a free localhost port and builds the playlist with `enqueue`/`add`. The binary comes from the `vlc_media_player_path` setting (falls back to `vlc`). A Windows `vlc.exe` run from WSL gets `wslpath -w` paths and `--rc-quiet`, and the RC commands go through `powershell.exe` because WSL in NAT mode cannot reach Windows' localhost. `ShowController` loads the current user's `watchProgress` row and `ShowResource` exposes it as `watch_progress` (object or `null`). "Continue watching" lists use `filter[has_watch_progress]=1&sort=-last_watched_at`. Tests mock `OpenVlcPlaylistAction`; its own test fakes `Process` and listens on the RC port.
- Status codes: store returns `->response()->setStatusCode(201)`, destroy returns `response()->noContent()`.
- Routes (`routes/api.php`, all under `v1`): read routes (`index`, `show`) go in the `auth:sanctum` group, write routes go in the `['auth:sanctum', 'admin']` group, with the same `apiResource` registered twice (`->only([...])` / `->except([...])`). Child resources use `apiResource('shows.links', ...)->shallow()`. Multi-word URIs are kebab-case (`show-sections`).
- Enum validation: `Rule::enum(XType::class)`; enums live in `app/Enums` as string-backed enums.
- Every action that writes to the database wraps its `handle()` body in `DB::transaction` (see the action-layer instructions); `tests/Unit/ActionTransactionTest.php` enforces it.
- Tests: Pest function style with `use function Pest\Laravel\{getJson, postJson, ...}`; `RefreshDatabase` is applied to `Feature` automatically. Use `actingAsAdmin()` for admin; for a regular user, create a token and call `withToken(...)`. Each resource usually covers: unauthenticated → 401, non-admin write → 403, list/show/create/update/delete happy paths, and validation 422s. Video-file tests: Feature tests already point `video_base_path` at a throwaway folder (cleaned after each test); use `putVideoFile($path)` to seed files, assert explicit paths, and cover the rollback case for any new flow that moves or deletes files.

## Commits

Conventional Commits, as in `commit-message-instructions.md`: `type(scope): imperative summary`, lowercase, no trailing period. Scopes are domain plurals, e.g. `shows`, `categories`, `episodes`, `show-links`, `settings`, `auth`, `api`.
