<?php

namespace App\Models\ShowTitle\Query;

use Spatie\QueryBuilder\AllowedFilter;

trait HasShowTitleQuery
{
    /**
     * @return array<int, string|AllowedFilter>
     */
    public static function getAllowedFilters(): array
    {
        return [
            'title',
            AllowedFilter::exact('show_id'),
            AllowedFilter::exact('is_primary'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function getAllowedSorts(): array
    {
        return [
            'id',
            'title',
            'is_primary',
            'created_at',
            'updated_at',
        ];
    }
}
