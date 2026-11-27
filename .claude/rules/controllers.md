---
paths:
  - 'app/Http/Controllers/**/*.php'
---

# Controller Rules

- Controllers coordinate HTTP flow only: type-hint the request class, extract `validated()`, call an action (injected into the method, `$action->handle(...)`), a query builder or, for a search that is only passed on, a service, and return a resource. A read of a few lines with one caller, such as a status, is a private method here, not an action.
- No validation rules, persistence workflows or domain branching here. Authorization is route middleware plus `$this->authorize(...)` policy checks.
- Use route-model binding instead of manual lookups.
- Return payloads through resource classes, never raw `response()->json([...])`, and no generic `message` fields.
- Store returns `->response()->setStatusCode(201)`; destroy returns `response()->noContent()`.
- If the resource embeds relations, eager-load them in every method (`->with(...)` in index, `->load(...)` elsewhere).
- Index endpoints use Spatie Query Builder with the model's query trait:

```php
$xs = QueryBuilder::for(X::query())
    ->allowedFilters(...X::getAllowedFilters())
    ->allowedSorts(...X::getAllowedSorts())
    ->jsonPaginate();

return XResource::collection($xs)->response();
```

- Create passes `CreateXData::from($request->validated())`. Update passes the bound model inside the DTO; multi-word models use a snake key:

```php
$updated = $action->handle(UpdateShowLinkData::from([...$request->validated(), 'show_link' => $link]));
```
