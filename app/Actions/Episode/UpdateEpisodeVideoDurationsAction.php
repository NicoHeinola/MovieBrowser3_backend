<?php

namespace App\Actions\Episode;

use App\Models\Episode\Episode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateEpisodeVideoDurationsAction
{
    use AsAction;

    /**
     * Stores read video lengths, keyed by episode id; null marks one that couldn't be read. Each episode leaves the queue.
     * An episode queued again since it was read, because its file was replaced meanwhile, stays queued for the new file.
     *
     * @param  Collection<int, Episode>  $episodes  as they were when read
     * @param  Collection<int, int|null>  $durations
     */
    public function handle(Collection $episodes, Collection $durations): void
    {
        DB::transaction(function () use ($episodes, $durations): void {
            foreach ($episodes as $episode) {
                Episode::query()
                    ->whereKey($episode->id)
                    ->where('video_duration_queued_at', $episode->video_duration_queued_at)
                    ->update([
                        'video_duration_seconds' => $durations[$episode->id],
                        'video_duration_queued_at' => null,
                    ]);
            }
        });
    }
}
