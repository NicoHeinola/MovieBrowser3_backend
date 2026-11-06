<?php

namespace App\Models\Show\Query;

use App\Enums\WatchStatus;
use App\Models\Query\CaseInsensitiveLike;
use App\Models\Show\Query\Filters\MetadataFilter;
use App\Models\Show\Query\Sorts\LastWatchedSort;
use App\Models\Show\Query\Sorts\PrimaryTitleSort;
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
            AllowedFilter::scope('entry_watch_status', 'withEntryWatchStatus'),
            AllowedFilter::scope('min_episode_count', 'withMinEpisodeCount'),
            AllowedFilter::scope('max_episode_count', 'withMaxEpisodeCount'),
            AllowedFilter::scope('has_missing_video_file', 'withMissingVideoFile'),
            AllowedFilter::scope('has_lost_video_file', 'withLostVideoFile'),
            AllowedFilter::scope('exclude_categories', 'withoutCategories'),
            AllowedFilter::scope('exclude_watch_status', 'withoutWatchStatus'),
            AllowedFilter::scope('exclude_entry_watch_status', 'withoutEntryWatchStatus'),
        ];
    }

    /**
     * Shows where every word of the term appears, ignoring case, in the description, a title, a category name or an entry name.
     */
    public function scopeSearch(Builder $query, string ...$terms): void
    {
        $relatedColumns = ['titles' => 'show_titles.title', 'categories' => 'categories.name', 'entries' => 'show_entries.name'];

        foreach (CaseInsensitiveLike::words(...$terms) as $word) {
            $query->where(function (Builder $query) use ($relatedColumns, $word) {
                CaseInsensitiveLike::where($query, 'shows.description', $word);

                foreach ($relatedColumns as $relation => $column) {
                    $query->orWhereHas($relation, function (Builder $query) use ($column, $word) {
                        CaseInsensitiveLike::where($query, $column, $word);
                    });
                }
            });
        }
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
     * Shows whose combined watch status for the authenticated user is one of the given values.
     * The combined status mirrors the frontend's getCombinedWatchStatus: the shared status when every entry has
     * the same one, `mixed` when statuses differ or only some entries have one, and null when no entry has one.
     */
    public function scopeWithWatchStatus(Builder $query, string ...$statuses): void
    {
        $query->where(function (Builder $query) use ($statuses) {
            foreach ($statuses as $status) {
                $query->orWhere(function (Builder $query) use ($status) {
                    $status === 'mixed' ? $query->withMixedWatchStatus() : $query->withUniformWatchStatus($status);
                });
            }
        });
    }

    /**
     * Shows where every entry has the given status from the authenticated user.
     */
    public function scopeWithUniformWatchStatus(Builder $query, string $status): void
    {
        // "No entry lacks the status" also holds for a show with no entries, so require at least one.
        $query->whereHas('entries')->whereDoesntHave('entries', function (Builder $query) use ($status) {
            $query->whereDoesntHave('watchStatuses', function (Builder $query) use ($status) {
                $query->where('user_id', Auth::id())->where('status', $status);
            });
        });
    }

    /**
     * Shows where the authenticated user marked at least one entry, but not every entry with the same status.
     */
    public function scopeWithMixedWatchStatus(Builder $query): void
    {
        $query->whereHas('entries.watchStatuses', function (Builder $query) {
            $query->where('user_id', Auth::id());
        })->whereNot(function (Builder $query) {
            foreach (WatchStatus::cases() as $status) {
                $query->orWhere(function (Builder $query) use ($status) {
                    $query->withUniformWatchStatus($status->value);
                });
            }
        });
    }

    /**
     * Shows with at least this many episodes across all their entries.
     */
    public function scopeWithMinEpisodeCount(Builder $query, int $count): void
    {
        $query->has('episodes', '>=', $count);
    }

    /**
     * Shows with at most this many episodes across all their entries. A show without episodes counts as 0.
     */
    public function scopeWithMaxEpisodeCount(Builder $query, int $count): void
    {
        $query->has('episodes', '<=', $count);
    }

    /**
     * Shows with at least one episode lacking its video file. Only `true` filters; any other value is ignored.
     */
    public function scopeWithMissingVideoFile(Builder $query, mixed $value = true): void
    {
        // Spatie turns the string 'true' into a boolean before calling the scope.
        if ($value === true || $value === 'true') {
            $query->whereHas('episodes', fn (Builder $query) => $query->missingVideoFile());
        }
    }

    /**
     * Shows with at least one episode whose uploaded video file is gone. Only `true` filters; any other value is ignored.
     */
    public function scopeWithLostVideoFile(Builder $query, mixed $value = true): void
    {
        if ($value === true || $value === 'true') {
            $query->whereHas('episodes', fn (Builder $query) => $query->lostVideoFile());
        }
    }

    /**
     * Shows with none of the given category values.
     */
    public function scopeWithoutCategories(Builder $query, string ...$values): void
    {
        $query->whereDoesntHave('categories', function (Builder $query) use ($values) {
            $query->whereIn('value', $values);
        });
    }

    /**
     * Shows whose combined watch status for the authenticated user is none of the given values.
     * A show without a combined status is never excluded.
     */
    public function scopeWithoutWatchStatus(Builder $query, string ...$statuses): void
    {
        $query->whereNot(function (Builder $query) use ($statuses) {
            $query->withWatchStatus(...$statuses);
        });
    }

    /**
     * Shows with at least one entry the authenticated user has marked with one of the given statuses.
     */
    public function scopeWithEntryWatchStatus(Builder $query, string ...$statuses): void
    {
        $query->whereHas('entries.watchStatuses', function (Builder $query) use ($statuses) {
            $query->where('user_id', Auth::id())->whereIn('status', $statuses);
        });
    }

    /**
     * Shows with no entry the authenticated user has marked with one of the given statuses.
     */
    public function scopeWithoutEntryWatchStatus(Builder $query, string ...$statuses): void
    {
        $query->whereDoesntHave('entries.watchStatuses', function (Builder $query) use ($statuses) {
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
            AllowedSort::custom('title', new PrimaryTitleSort),
        ];
    }
}
