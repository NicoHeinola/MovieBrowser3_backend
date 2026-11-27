---
paths:
  - 'app/Models/**/*.php'
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
- A background run kept as one row with a unique `running` flag gets its scopes in its own query trait: `running` for the video base folder move, `active` and `stale` for the video file check. A check whose worker died goes stale after `video_files.check_stale_after_seconds`, so a new check can start. A move needs no stale scope: its job fails on the attempt limit and ends the move through `failed()`.
