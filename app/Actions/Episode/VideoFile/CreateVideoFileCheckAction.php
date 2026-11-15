<?php

namespace App\Actions\Episode\VideoFile;

use App\Exceptions\VideoRelocationRunningException;
use App\Jobs\CheckEpisodeVideoFilesJob;
use App\Models\Episode\Episode;
use App\Models\VideoFileCheck\VideoFileCheck;
use App\Models\VideoRelocation\VideoRelocation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateVideoFileCheckAction
{
    use AsAction;

    /**
     * Starts a check unless one is running. Touches no disk: the scheduler calls this too and has no media folders,
     * so an unreachable base folder is left for the job to report.
     *
     * @throws VideoRelocationRunningException files on the move would look missing
     */
    public function handle(): void
    {
        if (VideoRelocation::query()->running()->exists()) {
            throw new VideoRelocationRunningException;
        }

        $check = DB::transaction(function (): VideoFileCheck {
            // A dead worker's check would hold the unique index forever. Deleting it also ends any job of it still queued.
            VideoFileCheck::query()->stale()->delete();

            // The unique index on `running` decides who wins, so two presses at once can't both start a check
            return VideoFileCheck::query()->createOrFirst(['running' => true], ['total' => Episode::query()->count()]);
        });

        if ($check->wasRecentlyCreated) {
            CheckEpisodeVideoFilesJob::dispatch($check);
        }
    }
}
