<?php

namespace App\Models\Download\Query;

use Closure;
use Illuminate\Database\Eloquent\Builder;

trait HasDownloadQuery
{
    /**
     * Counts the episodes made for the download, as `waiting_episode_count`: no video of their own, and their file not
     * in place yet. Cancelling can remove them. For `withCount` on a query or `loadCount` on a loaded download.
     *
     * @return array<string, Closure>
     */
    public static function waitingEpisodeCount(): array
    {
        return ['picks as waiting_episode_count' => fn (Builder $query) => $query
            ->whereNull('imported_at')
            ->whereHas('episode', fn (Builder $episode) => $episode->where('filename', ''))];
    }
}
