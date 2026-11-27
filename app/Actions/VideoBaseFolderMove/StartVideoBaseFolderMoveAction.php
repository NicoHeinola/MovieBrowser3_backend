<?php

namespace App\Actions\VideoBaseFolderMove;

use App\Actions\Episode\VideoFile\Path\ResolveVideoBaseFolderAction;
use App\Exceptions\VideoBaseFolderMoveRunningException;
use App\Exceptions\VideoFileCheckRunningException;
use App\Jobs\MoveVideoBaseFolderJob;
use App\Models\Episode\Episode;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use App\Models\VideoFileCheck\VideoFileCheck;
use Lorisleiva\Actions\Concerns\AsAction;

class StartVideoBaseFolderMoveAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveVideoBaseFolderAction $resolveVideoBaseFolderAction,
    ) {}

    /**
     * Starts to move all videos to a different video base folder. The job moves them, and changes the setting at the
     * end.
     *
     * @param  string  $toPath  The new video base folder
     *
     * @throws VideoBaseFolderMoveRunningException When another move runs
     * @throws VideoFileCheckRunningException When a check runs: it would think that the files in the move are missing
     */
    public function handle(string $toPath): VideoBaseFolderMove
    {
        if (VideoFileCheck::query()->active()->exists()) {
            throw new VideoFileCheckRunningException;
        }

        $videoCount = Episode::query()->withVideoFilename()->count();

        // The unique index on `running` lets only one move start, also when the admin clicks two times at once
        $folderMove = VideoBaseFolderMove::query()->createOrFirst(['running' => true], [
            'from_path' => $this->resolveVideoBaseFolderAction->handle(),
            'to_path' => rtrim($toPath, '/') ?: '/',
            'total' => $videoCount,
        ]);

        if (!$folderMove->wasRecentlyCreated) {
            throw new VideoBaseFolderMoveRunningException;
        }

        MoveVideoBaseFolderJob::dispatch($folderMove);

        return $folderMove;
    }
}
