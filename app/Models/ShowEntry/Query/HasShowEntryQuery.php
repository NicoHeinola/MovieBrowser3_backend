<?php

namespace App\Models\ShowEntry\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedFilter;

trait HasShowEntryQuery
{
    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::custom('show_id', new MetadataFilter),
            AllowedFilter::custom('type', new MetadataFilter),
            AllowedFilter::custom('name', new MetadataFilter),
            AllowedFilter::scope('watch_status', 'withWatchStatus'),
        ];
    }

    /**
     * Entries the authenticated user has marked with one of the given statuses.
     */
    public function scopeWithWatchStatus(Builder $query, string ...$statuses): void
    {
        $query->whereHas('watchStatuses', function (Builder $query) use ($statuses) {
            $query->where('user_id', Auth::id())->whereIn('status', $statuses);
        });
    }

    public static function getAllowedSorts(): array
    {
        return ['id', 'name', 'type', 'sort_order', 'created_at', 'updated_at'];
    }
}
