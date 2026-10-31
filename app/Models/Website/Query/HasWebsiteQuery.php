<?php

namespace App\Models\Website\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

trait HasWebsiteQuery
{
    /**
     * @return array<int, string|AllowedFilter>
     */
    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::custom('url', new MetadataFilter),
            AllowedFilter::custom('title', new MetadataFilter),
            AllowedFilter::custom('description', new MetadataFilter),
            AllowedFilter::custom('tags.name', new MetadataFilter),
            AllowedFilter::scope('tags.id', 'withTags'),
            AllowedFilter::scope('exclude_tags', 'withoutTags'),
        ];
    }

    /**
     * Websites with every one of the given tag ids; non-numeric ids match nothing.
     */
    public function scopeWithTags(Builder $query, int|string ...$ids): void
    {
        foreach (array_map('intval', $ids) as $id) {
            $query->whereHas('tags', function (Builder $query) use ($id) {
                $query->where('website_tags.id', $id);
            });
        }
    }

    /**
     * Websites with none of the given tag ids; non-numeric ids are ignored.
     */
    public function scopeWithoutTags(Builder $query, int|string ...$ids): void
    {
        $query->whereDoesntHave('tags', function (Builder $query) use ($ids) {
            $query->whereIn('website_tags.id', array_map('intval', $ids));
        });
    }

    /**
     * @return array<int, string>
     */
    public static function getAllowedSorts(): array
    {
        return ['id', 'url', 'title', 'created_at', 'updated_at'];
    }
}
