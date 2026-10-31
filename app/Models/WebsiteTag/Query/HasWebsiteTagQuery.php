<?php

namespace App\Models\WebsiteTag\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use Spatie\QueryBuilder\AllowedFilter;

trait HasWebsiteTagQuery
{
    /**
     * @return array<int, string|AllowedFilter>
     */
    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::custom('name', new MetadataFilter),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function getAllowedSorts(): array
    {
        return ['id', 'name', 'created_at', 'updated_at'];
    }
}
