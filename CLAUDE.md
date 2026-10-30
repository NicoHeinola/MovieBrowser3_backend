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
- Models declare fillable with the `#[Fillable([...])]` attribute, use `HasFactory` with an explicit `newFactory()` (factories live in `Database\Factories`, not next to the model), and use `protected $casts` for enums.
- `JsonResource::withoutWrapping()` is global: single resources are unwrapped; paginated collections have `data`. Resources have a `/** @mixin Model */` docblock and nest relations through `whenLoaded`.
- Index endpoints: `QueryBuilder::for(...)->allowedFilters(...X::getAllowedFilters())->allowedSorts(...)->jsonPaginate()`. Filters use `AllowedFilter::custom('col', new MetadataFilter)` (`App\Models\Show\Query\Filters\MetadataFilter`). Commas in filter values are literal; multiple values use `filter[col][]=...`.
- Status codes: store returns `->response()->setStatusCode(201)`, destroy returns `response()->noContent()`.
- Routes (`routes/api.php`, all under `v1`): read routes (`index`, `show`) go in the `auth:sanctum` group, write routes go in the `['auth:sanctum', 'admin']` group, with the same `apiResource` registered twice (`->only([...])` / `->except([...])`). Child resources use `apiResource('shows.links', ...)->shallow()`. Multi-word URIs are kebab-case (`show-sections`).
- Enum validation: `Rule::enum(XType::class)`; enums live in `app/Enums` as string-backed enums.
- Use `DB::transaction` in actions that write more than one row.
- Tests: Pest function style with `use function Pest\Laravel\{getJson, postJson, ...}`; `RefreshDatabase` is applied to `Feature` automatically. Use `actingAsAdmin()` for admin; for a regular user, create a token and call `withToken(...)`. Each resource usually covers: unauthenticated → 401, non-admin write → 403, list/show/create/update/delete happy paths, and validation 422s.

## Commits

Conventional Commits, as in `commit-message-instructions.md`: `type(scope): imperative summary`, lowercase, no trailing period. Scopes are domain plurals, e.g. `shows`, `categories`, `episodes`, `show-links`, `settings`, `auth`, `api`.
