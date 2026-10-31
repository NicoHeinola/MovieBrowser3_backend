<?php

namespace App\Models\Show\Query\Sorts;

use App\Models\ShowTitle\ShowTitle;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * Orders shows by their primary title, ignoring case; shows without a primary title come last either way.
 */
class PrimaryTitleSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $primaryTitle = ShowTitle::query()
            ->selectRaw('lower(title)')
            ->whereColumn('show_titles.show_id', 'shows.id')
            ->where('is_primary', true)
            ->orderBy('id')
            ->limit(1)
            ->toBase();

        $direction = $descending ? 'desc' : 'asc';

        $query->orderByRaw("({$primaryTitle->toSql()}) {$direction} nulls last", $primaryTitle->getBindings());
    }
}
