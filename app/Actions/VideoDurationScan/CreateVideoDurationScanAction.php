<?php

namespace App\Actions\VideoDurationScan;

use App\Exceptions\VideoRelocationRunningException;
use App\Jobs\ReadEpisodeVideoDurationsJob;
use App\Models\Episode\Episode;
use App\Models\VideoDurationScan\VideoDurationScan;
use App\Models\VideoRelocation\VideoRelocation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateVideoDurationScanAction
{
    use AsAction;

    /**
     * Starts a scan for every queued episode, or lets the running one take them too. Does nothing while none waits.
     * Queuing episodes is `QueueEpisodeVideoDurationsAction`'s job; this only runs what is already queued, such as
     * episodes a scan that lost the drive left behind.
     *
     * @throws VideoRelocationRunningException files on the move can't be read
     */
    public function handle(): void
    {
        if (VideoRelocation::query()->running()->exists()) {
            throw new VideoRelocationRunningException;
        }

        $scan = DB::transaction(function (): ?VideoDurationScan {
            $queued = Episode::query()->whereNotNull('video_duration_queued_at')->count();

            if ($queued === 0) {
                return null;
            }

            // A dead worker's scan would hold the unique index forever. Deleting it also ends any job of it still queued.
            VideoDurationScan::query()->stale()->delete();

            // The unique index on `running` decides who wins, so two starts at once can't both run a scan
            $scan = VideoDurationScan::query()->createOrFirst(['running' => true], ['total' => $queued]);

            // A running scan reads the new episodes too, so its total grows by them
            if (!$scan->wasRecentlyCreated) {
                $scan->update(['total' => $scan->read + $queued]);
            }

            return $scan;
        });

        if ($scan?->wasRecentlyCreated) {
            // After the caller's transaction too, so the job never looks for episodes queued but not yet committed
            ReadEpisodeVideoDurationsJob::dispatch($scan)->afterCommit();
        }
    }
}
