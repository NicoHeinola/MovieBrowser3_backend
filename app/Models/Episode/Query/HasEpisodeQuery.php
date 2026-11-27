<?php

namespace App\Models\Episode\Query;

use App\Models\Show\Query\Filters\MetadataFilter;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

trait HasEpisodeQuery
{
    /**
     * Episodes that were given a video file, whether it's still there or not.
     */
    public function scopeWithVideoFilename(Builder $query): void
    {
        $query->where('filename', '!=', '');
    }

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
        $query->withVideoFilename()->where('has_video_file', false);
    }

    /**
     * Episodes with a file but no length: not read yet, or unreadable.
     */
    public function scopeUnknownVideoDuration(Builder $query): void
    {
        $query->withVideoFilename()->where('has_video_file', true)->whereNull('video_duration_seconds');
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
