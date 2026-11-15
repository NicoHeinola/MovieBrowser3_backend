<?php

namespace App\Jobs;

use App\Actions\VideoRelocation\UpdateVideoRelocationFailureAction;
use App\Actions\VideoRelocation\UpdateVideoRelocationProgressAction;
use App\Actions\VideoRelocation\UpdateVideoRelocationResultAction;
use App\Exceptions\VideoFileMoveException;
use App\Models\VideoRelocation\VideoRelocation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Moves one episode's video per job and queues the next, so VLC tracking, which shares the worker, keeps running in
 * between. Any error, or a worker that died mid-file (its job comes back after `retry_after` and fails on the attempt
 * limit), ends up in `failed()`.
 */
class RelocateVideoFilesJob implements ShouldQueue
{
    use Queueable;

    // A copy between disks can outlast the worker's default 60 second timeout; 0 turns it off for this job
    public int $timeout = 0;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public VideoRelocation $relocation,
        public int $afterId = 0,
    ) {}

    public function handle(
        UpdateVideoRelocationProgressAction $updateVideoRelocationProgressAction,
        UpdateVideoRelocationResultAction $updateVideoRelocationResultAction,
    ): void {
        $nextAfterId = $updateVideoRelocationProgressAction->handle($this->relocation, $this->afterId);

        if ($nextAfterId === null) {
            $updateVideoRelocationResultAction->handle($this->relocation);

            return;
        }

        self::dispatch($this->relocation, $nextAfterId);
    }

    public function failed(?Throwable $exception = null): void
    {
        UpdateVideoRelocationFailureAction::run(
            $this->relocation,
            $exception instanceof VideoFileMoveException ? $exception->path : null,
        );
    }
}
