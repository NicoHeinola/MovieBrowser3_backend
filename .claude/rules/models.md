---
description: Model folder conventions for Laravel models and their Query/Relations traits. Use when creating or editing files under app/Models.
paths:
  - 'app/Models/**/*.php'
applyTo: 'app/Models/**/*.php'
---

# Model Rules

- Each model has its own folder `app/Models/<X>/` with `X.php` at the root, relationship methods in `Relations/HasXRelations.php` and scopes plus Query Builder config in `Query/HasXQuery.php`.
- Models represent the database only: fillable, casts, relations, query traits, factory wiring, and accessors or mutators that shape how a column is stored (e.g. `Setting::value()` casting by its `type` column). Paths, setting lookups, memoization and validation helpers belong in actions or `App\Rules`.
- Declare fillable with `#[Fillable([...])]`, cast enums in `protected $casts`, and wire factories from `Database\Factories` explicitly:

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

## Query Traits

- Expose `getAllowedFilters()` and `getAllowedSorts()`; put scopes (`scopeSearch`, `scopeWithTags`) here too. Never parse request input or shape responses.
- Filters use `AllowedFilter::custom('col', new MetadataFilter)` (`App\Models\Show\Query\Filters\MetadataFilter`) rather than `partial` or `exact`. It supports operator prefixes (`like:`, `eq:`, `null`) and relation dot notation (`titles.title`).
- Text search scopes match with `App\Models\Query\CaseInsensitiveLike::where()` (lowercases both sides, escapes `%` and `_`), and ad-hoc `like` filters use `whereLike()`. Never `where($col, 'like', …)`: Postgres `like` is case-sensitive while the SQLite tests are not, so it passes tests and breaks in dev.
- Filter values are not split on commas; multiple values use `filter[col][]=a&filter[col][]=b`, each applied as an AND condition.
