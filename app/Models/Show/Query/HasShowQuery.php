<?php

namespace App\Models\Show\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use App\Models\Show\Query\Sorts\RandomSort;
use Illuminate\Database\Eloquent\Builder;
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
            AllowedFilter::partial('title', 'titles.title'),
        ];
    }

    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereHas('titles', function (Builder $query) use ($value) {
            $query->where('title', 'like', "%{$value}%");
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
        ];
    }
}
