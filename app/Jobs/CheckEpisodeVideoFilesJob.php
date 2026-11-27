<?php

namespace App\Jobs;

use App\Actions\VideoFileCheck\CheckEpisodeVideoFilesAction;
use App\Actions\VideoFileCheck\FinishVideoFileCheckAction;
use App\Enums\VideoFileCheckErrorCode;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Checks every episode's video file. VLC tracking shares the worker and waits for it, which is fine: it takes seconds.
 */
class CheckEpisodeVideoFilesJob implements ShouldQueue
{
    use Queueable;

    // A slow drive can outlast the worker's default 60 second timeout; 0 turns it off for this job
    public int $timeout = 0;

    // A check deleted as stale simply ends here.
    public bool $deleteWhenMissingModels = true;

    public function __construct(public VideoFileCheck $check) {}

    public function handle(CheckEpisodeVideoFilesAction $checkEpisodeVideoFilesAction): void
    {
        $checkEpisodeVideoFilesAction->handle($this->check);
    }

    /**
     * Ends the check so it shows as failed; only a killed worker needs the stale check.
     */
    public function failed(): void
    {
        FinishVideoFileCheckAction::run($this->check, VideoFileCheckErrorCode::Failed);
    }
}
