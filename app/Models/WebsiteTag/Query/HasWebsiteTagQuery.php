<?php

namespace App\Models\WebsiteTag\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

trait HasWebsiteTagQuery
{
    /**
     * @return array<int, string|AllowedFilter>
     */
    public static function getAllowedFilters(): array
    {
        return [
            AllowedFilter::scope('search', 'search'),
            AllowedFilter::custom('name', new MetadataFilter),
        ];
    }

    /**
     * Tags whose name contains every word of the term, ignoring case.
     */
    public function scopeSearch(Builder $query, string ...$terms): void
    {
        $words = preg_split('/\s+/', implode(' ', $terms), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($word)).'%';

            $query->whereRaw("lower(website_tags.name) like ? escape '!'", [$pattern]);
        }
    }

    /**
     * @return array<int, string>
     */
    public static function getAllowedSorts(): array
    {
        return ['id', 'name', 'created_at', 'updated_at'];
    }
}
