<?php

namespace App\Models\Website\Query;

use App\Models\Query\CaseInsensitiveLike;
use App\Models\Show\Query\Filters\MetadataFilter;
use App\Models\Website\Query\Sorts\TitleOrUrlSort;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

trait HasWebsiteQuery
{
    /**
     * @return array<int, string|AllowedFilter>
     */
    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::scope('search', 'search'),
            AllowedFilter::custom('url', new MetadataFilter),
            AllowedFilter::custom('title', new MetadataFilter),
            AllowedFilter::custom('description', new MetadataFilter),
            AllowedFilter::custom('is_favorite', new MetadataFilter),
            AllowedFilter::custom('tags.name', new MetadataFilter),
            AllowedFilter::scope('tags.id', 'withTags'),
            AllowedFilter::scope('exclude_tags', 'withoutTags'),
        ];
    }

    /**
     * Websites where every word of the term appears, ignoring case, in the title, url, description or a tag name.
     */
    public function scopeSearch(Builder $query, string ...$terms): void
    {
        foreach (CaseInsensitiveLike::words(...$terms) as $word) {
            $query->where(function (Builder $query) use ($word) {
                foreach (['websites.title', 'websites.url', 'websites.description'] as $column) {
                    CaseInsensitiveLike::where($query, $column, $word, 'or');
                }

                $query->orWhereHas('tags', function (Builder $query) use ($word) {
                    CaseInsensitiveLike::where($query, 'website_tags.name', $word);
                });
            });
        }
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
     * @return array<int, string|AllowedSort>
     */
    public static function getAllowedSorts(): array
    {
        return ['id', 'url', 'created_at', 'updated_at', AllowedSort::custom('title', new TitleOrUrlSort)];
    }
}
