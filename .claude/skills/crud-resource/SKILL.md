---
name: crud-resource
description: Scaffold or extend a CRUD API resource in this Laravel backend (migration, model + Query/Relations traits, factory, DTOs, actions, form requests, resource, controller, routes, Pest feature test). Use when adding a new entity/endpoint set like categories, show-sections, show-links, or a nested child resource of shows.
---

# CRUD Resource

Build a full vertical slice for a domain `X` (StudlyCase, e.g. `ShowSection`; table `show_sections`; URI `show-sections`). Structural rules live in `CLAUDE.md` and `.github/instructions/`. This skill gives the order and the templates. When unsure, copy from the closest existing slice:

- Top-level resource: `Category` (simplest) or `ShowSection`
- Shallow child of `shows`: `ShowLink` / `ShowTitle` / `ShowEntry`
- Enum column: `ShowLink` (`app/Enums/ShowLinkType.php`, `Rule::enum`, `$casts`)

## Workflow

1. **Migration**: `database/migrations/YYYY_MM_DD_HHMMSS_create_xs_table.php`, an anonymous class. Use `foreignId(...)->constrained()->cascadeOnDelete()` and explicit `nullable()`, plus a unique/index where needed. Make the timestamp sort after the latest existing migration. One migration creates or changes one table only; a child table gets its own migration.
2. **Model**: `app/Models/X/X.php`, plus the `Query/HasXQuery.php` and `Relations/HasXRelations.php` traits. Add the inverse relation to the related model's Relations trait.
3. **Factory**: `database/factories/XFactory.php` with `protected $model = X::class;`. Related models go in as `Related::factory()`. If local data needs it, add a seeder and register it in `DatabaseSeeder`.
4. **DTOs**: `app/Dtos/X/CreateXData.php` and `UpdateXData.php`.
5. **Actions**: `app/Actions/X/{Create,Update,Delete}XAction.php`.
6. **Requests**: `app/Http/Requests/X/{Store,Update}XRequest.php`.
7. **Resource**: `app/Http/Resources/X/XResource.php`. If a parent resource embeds it, add it there with `whenLoaded`.
8. **Controller**: `app/Http/Controllers/XController.php`.
9. **Routes**: register `apiResource` twice in `routes/api.php`, with reads under `auth:sanctum` and writes under `['auth:sanctum', 'admin']`.
10. **Tests**: `tests/Feature/XApiTest.php`.
11. Run `composer lint:fix`, then `php artisan test --compact --filter=XApiTest`, then the full suite if shared models or factories changed.

## Templates

Model:

```php
#[Fillable(['category_id', 'description', 'sort_order'])]
class X extends Model
{
    /** @use HasFactory<XFactory> */
    use HasFactory;

    use HasXQuery;
    use HasXRelations;

    protected static function newFactory(): XFactory
    {
        return XFactory::new();
    }
}
```

Relations trait: `/** @mixin Model */ trait HasXRelations { public function category(): BelongsTo { ... } }`

Query trait:

```php
public static function getAllowedFilters(): array
{
    return [AllowedFilter::custom('name', new MetadataFilter)];
}

public static function getAllowedSorts(): array
{
    return ['id', 'sort_order', 'created_at', 'updated_at'];
}
```

DTOs (camelCase props; input is snake_case-mapped globally):

```php
class CreateXData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {}
}

#[MapOutputName(SnakeCaseMapper::class)]
class UpdateXData extends Data
{
    public function __construct(
        public X $x,                              // the bound model
        public string|Optional $name,
        public string|Optional|null $description, // clearable: keep Optional!
    ) {}
}
```

Actions:

```php
class UpdateXAction
{
    use AsAction;

    public function handle(UpdateXData $data): X
    {
        return DB::transaction(function () use ($data): X {
            $data->x->fill(Arr::only($data->all(), $data->x->getFillable()))->save();

            return $data->x->fresh();
        });
    }
}
```

Create uses `X::query()->create([... explicit snake_case keys ...])` and delete calls `$x->delete()`, both inside `DB::transaction` like every write action. A child create takes the parent first: `handle(Show $show, CreateXData $data)`.

Requests: `rules()` only, with no `authorize()`. Add the `/** @return array<string, list<mixed>> */` docblock. For update, prefix rules with `'sometimes'` and read the bound model with `/** @var X $x */ $x = $this->route('x');` for `Rule::unique(...)->ignore($x->id)`.

Controller:

```php
public function index(): JsonResponse
{
    $xs = QueryBuilder::for(X::query())
        ->allowedFilters(...X::getAllowedFilters())
        ->allowedSorts(...X::getAllowedSorts())
        ->jsonPaginate();

    return XResource::collection($xs)->response();
}

public function store(StoreXRequest $request, CreateXAction $action): JsonResponse
{
    $x = $action->handle(CreateXData::from($request->validated()));

    return XResource::make($x)->response()->setStatusCode(201);
}

public function update(X $x, UpdateXRequest $request, UpdateXAction $action): JsonResponse
{
    $updatedX = $action->handle(UpdateXData::from([...$request->validated(), 'x' => $x]));

    return XResource::make($updatedX)->response();
}

public function destroy(X $x, DeleteXAction $action): Response
{
    $action->handle($x);

    return response()->noContent();
}
```

If the resource embeds relations, eager-load them in every method (`->with(...)` in index, `->load(...)` elsewhere).

Routes:

```php
// inside Route::middleware('auth:sanctum')
Route::apiResource('xs', XController::class)->only(['index', 'show']);
// inside Route::middleware(['auth:sanctum', 'admin'])
Route::apiResource('xs', XController::class)->except(['index', 'show']);
// child of shows: apiResource('shows.xs', ...)->shallow()->only/except(...)
```

## Test coverage checklist (Pest, function style)

- Guests get 401 on every endpoint.
- A non-admin (`User::factory()->create()` + `withToken($user->createToken('test-token')->plainTextToken)`) gets 403 on writes.
- Authenticated list (`assertJsonCount(n, 'data')`) and show (unwrapped `assertJsonPath('id', ...)`).
- Admin (`actingAsAdmin()`) create → `assertCreated()` + `assertDatabaseHas`.
- Partial update changes only the sent fields, and an omitted nullable field stays intact.
- Delete → `assertNoContent()` + `assertDatabaseMissing`. Check the cascade if relevant.
- 422 for each meaningful validation rule (required, unique, exists, enum).
- Filters and sorts that the query trait exposes.

## Done when

- All files above exist and follow the layout. The inverse relation and factory are wired.
- Pint is clean and the feature test plus the full suite pass.
- If the slice introduced a new durable pattern, `.github/instructions/*` (and `CLAUDE.md` if relevant) were updated.
