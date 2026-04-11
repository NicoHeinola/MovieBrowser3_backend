<?php

namespace App\Models\Show\Query;

use App\Models\Show\Query\Sorts\RandomSort;
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
            'banner_url',
            'card_image_url',
            'preview_url',
            AllowedFilter::partial('title', 'titles.title'),
        ];
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
