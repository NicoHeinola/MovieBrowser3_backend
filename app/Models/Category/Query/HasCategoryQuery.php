<?php

namespace App\Models\Category\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use Spatie\QueryBuilder\AllowedFilter;

trait HasCategoryQuery
{
    /**
     * @return array<int, string|AllowedFilter>
     */
    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::custom('name', new MetadataFilter),
            AllowedFilter::custom('value', new MetadataFilter),
            AllowedFilter::custom('icon', new MetadataFilter),
        ];
    }
}
