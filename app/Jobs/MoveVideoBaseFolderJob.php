<?php

namespace App\Jobs;

use App\Actions\VideoBaseFolderMove\ContinueVideoBaseFolderMoveAction;
use App\Actions\VideoBaseFolderMove\RevertVideoBaseFolderMoveAction;
use App\Exceptions\VideoFileMoveException;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Moves one episode's video per job and queues the next, so VLC tracking, which shares the worker, keeps running in
 * between. Any error, or a worker that died mid-file (its job comes back after `retry_after` and fails on the attempt
 * limit), ends up in `failed()`.
 */
class MoveVideoBaseFolderJob implements ShouldQueue
{
    use Queueable;

    // A copy between disks can outlast the worker's default 60 second timeout; 0 turns it off for this job
    public int $timeout = 0;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public VideoBaseFolderMove $folderMove,
        public int $afterId = 0,
    ) {}

    public function handle(ContinueVideoBaseFolderMoveAction $continueVideoBaseFolderMoveAction): void
    {
        $movedEpisodeId = $continueVideoBaseFolderMoveAction->handle($this->folderMove, $this->afterId);

        if ($movedEpisodeId !== null) {
            self::dispatch($this->folderMove, $movedEpisodeId);
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        $failedPath = null;

        if ($exception instanceof VideoFileMoveException) {
            $failedPath = $exception->path;
        }

        RevertVideoBaseFolderMoveAction::run($this->folderMove, $failedPath);
    }
}
