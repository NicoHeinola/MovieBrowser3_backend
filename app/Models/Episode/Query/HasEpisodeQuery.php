<?php

namespace App\Models\Episode\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use Spatie\QueryBuilder\AllowedFilter;

trait HasEpisodeQuery
{
    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::custom('show_entry_id', new MetadataFilter),
            AllowedFilter::custom('name', new MetadataFilter),
        ];
    }

    public static function getAllowedSorts(): array
    {
        return ['id', 'name', 'sequence_number', 'created_at', 'updated_at'];
    }
}
