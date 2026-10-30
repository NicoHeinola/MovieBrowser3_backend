<?php

namespace App\Models\ShowSection\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use Spatie\QueryBuilder\AllowedFilter;

trait HasShowSectionQuery
{
    /**
     * @return array<int, string|AllowedFilter>
     */
    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::custom('category_id', new MetadataFilter),
            AllowedFilter::custom('description', new MetadataFilter),
            AllowedFilter::custom('sort_order', new MetadataFilter),
            AllowedFilter::custom('category.name', new MetadataFilter),
            AllowedFilter::custom('category.value', new MetadataFilter),
        ];
    }

    /**
     * @return list<string>
     */
    public static function getAllowedSorts(): array
    {
        return ['id', 'category_id', 'sort_order', 'created_at', 'updated_at'];
    }
}
