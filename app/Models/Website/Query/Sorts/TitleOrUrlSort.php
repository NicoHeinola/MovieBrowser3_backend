<?php

namespace App\Models\Website\Query\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * Orders websites by title, ignoring case; websites without a title sort by their url without the scheme and a leading "www.",
 * matching the hostname the frontend shows. Plain case/substr instead of regexp_replace, which SQLite lacks; urls are always http(s).
 */
class TitleOrUrlSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $withoutScheme = "case when lower(websites.url) like 'https://%' then substr(websites.url, 9)"
            ." when lower(websites.url) like 'http://%' then substr(websites.url, 8) else websites.url end";
        $withoutWww = "case when lower({$withoutScheme}) like 'www.%' then substr({$withoutScheme}, 5) else {$withoutScheme} end";

        $direction = $descending ? 'desc' : 'asc';

        $query->orderByRaw("lower(coalesce(nullif(websites.title, ''), {$withoutWww})) {$direction}");
    }
}
