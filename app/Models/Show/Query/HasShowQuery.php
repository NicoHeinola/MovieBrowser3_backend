<?php

namespace App\Models\Show\Query;

use Spatie\QueryBuilder\AllowedFilter;

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
     * @return array<int, string>
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
        ];
    }
}
