<?php

namespace App\Models\Show\Query\Sorts;

use App\Models\ShowWatchProgress\ShowWatchProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * Orders shows by when the authenticated user last started playback; shows without progress come last either way.
 */
class LastWatchedSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $lastWatchedAt = ShowWatchProgress::query()
            ->select('updated_at')
            ->whereColumn('show_watch_progress.show_id', 'shows.id')
            ->where('user_id', Auth::id())
            ->toBase();

        $direction = $descending ? 'desc' : 'asc';

        $query->orderByRaw("({$lastWatchedAt->toSql()}) {$direction} nulls last", $lastWatchedAt->getBindings());
    }
}
