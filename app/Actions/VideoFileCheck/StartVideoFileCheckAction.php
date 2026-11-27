<?php

namespace App\Actions\VideoFileCheck;

use App\Exceptions\VideoBaseFolderMoveRunningException;
use App\Jobs\CheckEpisodeVideoFilesJob;
use App\Models\Episode\Episode;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use App\Models\VideoFileCheck\VideoFileCheck;
use Lorisleiva\Actions\Concerns\AsAction;

class StartVideoFileCheckAction
{
    use AsAction;

    /**
     * Starts a check of every episode's video file. Does nothing while one runs: a second one would find the same.
     *
     * @throws VideoBaseFolderMoveRunningException files on the move look missing
     */
    public function handle(): void
    {
        if (VideoBaseFolderMove::query()->running()->exists()) {
            throw new VideoBaseFolderMoveRunningException;
        }

        // A dead worker's check would hold the unique index forever. Deleting it also ends any job of it still queued
        VideoFileCheck::query()->stale()->delete();

        // The unique index decides who wins, so two starts at once can't both run one
        $episodeCount = Episode::query()->count();
        $check = VideoFileCheck::query()->createOrFirst(['running' => true], ['total' => $episodeCount]);

        if ($check->wasRecentlyCreated) {
            CheckEpisodeVideoFilesJob::dispatch($check);
        }
    }
}
