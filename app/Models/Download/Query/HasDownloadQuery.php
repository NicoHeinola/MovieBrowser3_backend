<?php

namespace App\Models\Download\Query;

use Closure;
use Illuminate\Database\Eloquent\Builder;

trait HasDownloadQuery
{
    /**
     * Counts a download's picks, for `withCount` on a query or `loadCount` on a loaded download:
     * - `picked_file_count`: all of them;
     * - `imported_file_count`: those that are their episode's video;
     * - `waiting_episode_count`: the episodes made for the download, with no video of their own and their file not in
     *   place yet. Cancelling can remove them.
     *
     * @return array<string, Closure>
     */
    public static function getPickCounts(): array
    {
        return [
            'picks as picked_file_count' => fn (Builder $query) => $query,
            'picks as imported_file_count' => fn (Builder $query) => $query->whereNotNull('imported_at'),
            'picks as waiting_episode_count' => fn (Builder $query) => $query
                ->whereNull('imported_at')
                ->whereHas('episode', fn (Builder $episode) => $episode->where('filename', '')),
        ];
    }
}
