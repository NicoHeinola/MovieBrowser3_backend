<?php

namespace App\Models\Episode\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

trait HasEpisodeQuery
{
    /**
     * Episodes without a playable file: the file went missing, or none was ever uploaded.
     */
    public function scopeMissingVideoFile(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->where('has_video_file', false)->orWhere('filename', ''));
    }

    /**
     * Episodes whose uploaded file is gone, unlike episodes that never had one.
     */
    public function scopeLostVideoFile(Builder $query): void
    {
        $query->where('has_video_file', false)->where('filename', '!=', '');
    }

    /**
     * Episodes with a file but no length: never read, unreadable, or left waiting by a scan that stopped.
     */
    public function scopeUnknownVideoDuration(Builder $query): void
    {
        $query->where('has_video_file', true)
            ->where('filename', '!=', '')
            ->whereNull('video_duration_seconds');
    }

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
