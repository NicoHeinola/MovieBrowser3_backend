<?php

namespace App\Models\Show\Query\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * Orders shows by the stored size of their video files; shows without any count as 0, and ties fall back to id.
 */
class VideoFilesBytesSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $direction = $descending ? 'desc' : 'asc';
        $measured = fn (Builder $query) => $query->where('has_video_file', true)->whereNotNull('video_file_size');

        $query
            ->withExists(['episodes as has_measured_video_files' => $measured])
            ->withSum(['episodes as video_files_bytes' => $measured], 'video_file_size')
            // The sum is null for shows with nothing measured, and SQLite and Postgres place nulls at opposite ends,
            // so the exists flag groups those shows first: they sort as 0 on both databases
            ->orderBy('has_measured_video_files', $direction)
            ->orderBy('video_files_bytes', $direction)
            // Keeps pages stable when many shows share a size, e.g. 0 before the first scan
            ->orderBy('shows.id');
    }
}
