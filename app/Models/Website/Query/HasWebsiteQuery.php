<?php

namespace App\Models\Website\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
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
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function getAllowedSorts(): array
    {
        return ['id', 'url', 'title', 'created_at', 'updated_at'];
    }
}
