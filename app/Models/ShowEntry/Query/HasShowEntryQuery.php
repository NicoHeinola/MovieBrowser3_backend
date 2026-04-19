<?php

namespace App\Models\ShowEntry\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use Spatie\QueryBuilder\AllowedFilter;

trait HasShowEntryQuery
{
    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::custom('show_id', new MetadataFilter),
            AllowedFilter::custom('type', new MetadataFilter),
            AllowedFilter::custom('name', new MetadataFilter),
        ];
    }

    public static function getAllowedSorts(): array
    {
        return ['id', 'name', 'type', 'sort_order', 'created_at', 'updated_at'];
    }
}
