<?php

namespace App\Models\Show\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use App\Models\Show\Query\Sorts\LastWatchedSort;
use App\Models\Show\Query\Sorts\RandomSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

trait HasShowQuery
{
    /**
     * @return array<int, string|AllowedFilter>
     */
    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::custom('banner_url', new MetadataFilter),
            AllowedFilter::custom('card_image_url', new MetadataFilter),
            AllowedFilter::custom('preview_url', new MetadataFilter),
            AllowedFilter::scope('search', 'search'),
            AllowedFilter::custom('title', new MetadataFilter, 'titles.title'),
            AllowedFilter::custom('categories.value', new MetadataFilter, 'categories.value'),
            AllowedFilter::scope('has_watch_progress', 'withWatchProgress'),
            AllowedFilter::scope('watch_status', 'withWatchStatus'),
        ];
    }

    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereHas('titles', function (Builder $query) use ($value) {
            $query->where('title', 'like', "%{$value}%");
        });
    }

    /**
     * Shows the authenticated user has (or, with false, has not) started playing.
     */
    public function scopeWithWatchProgress(Builder $query, bool $has = true): void
    {
        $query->{$has ? 'whereHas' : 'whereDoesntHave'}('watchProgress', function (Builder $query) {
            $query->where('user_id', Auth::id());
        });
    }

    /**
     * Shows with at least one entry the authenticated user has marked with one of the given statuses.
     */
    public function scopeWithWatchStatus(Builder $query, string ...$statuses): void
    {
        $query->whereHas('entries.watchStatuses', function (Builder $query) use ($statuses) {
            $query->where('user_id', Auth::id())->whereIn('status', $statuses);
        });
    }

    /**
     * @return array<int, string|AllowedSort>
     */
    public static function getAllowedSorts(): array
    {
        return [
            'id',
            'banner_url',
            'card_image_url',
            'preview_url',
            'created_at',
            'updated_at',
            AllowedSort::custom('random', new RandomSort),
            AllowedSort::custom('last_watched_at', new LastWatchedSort),
        ];
    }
}
