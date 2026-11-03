<?php

namespace App\Actions\ShowWatchProgress;

use App\Dtos\ShowWatchProgress\WatchPositionData;
use App\Models\Episode\Episode;
use App\Models\ShowWatchProgress\ShowWatchProgress;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowWatchProgressAction
{
    use AsAction;

    /**
     * Remembers the episode as where the user is in its show (one row per user and show), with how far into the
     * episode they are and how long it is. No position (or null fields) clears them, e.g. when VLC hasn't reported them yet.
     */
    public function handle(Episode $episode, User $user, ?WatchPositionData $position = null): ShowWatchProgress
    {
        return DB::transaction(function () use ($episode, $user, $position): ShowWatchProgress {
            $entry = $episode->entry;

            $progress = ShowWatchProgress::query()->updateOrCreate(
                ['show_id' => $entry->show_id, 'user_id' => $user->id],
                [
                    'show_entry_id' => $entry->id,
                    'episode_id' => $episode->id,
                    'position_seconds' => $position?->positionSeconds,
                    'duration_seconds' => $position?->durationSeconds,
                ],
            );

            // Watching the same episode again changes no column, but it still counts as the latest watch.
            $progress->touch();

            return $progress;
        });
    }
}
