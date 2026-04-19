<?php

namespace App\Models\ShowLink\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use Spatie\QueryBuilder\AllowedFilter;

trait HasShowLinkQuery
{
    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::custom('source_show_id', new MetadataFilter),
            AllowedFilter::custom('target_show_id', new MetadataFilter),
            AllowedFilter::custom('type', new MetadataFilter),
        ];
    }

    public static function getAllowedSorts(): array
    {
        return ['id', 'type', 'created_at', 'updated_at'];
    }
}
