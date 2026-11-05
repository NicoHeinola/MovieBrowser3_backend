<?php

namespace App\Jobs;

use App\Actions\Episode\VideoFile\CheckEpisodeVideoFilesAction;
use App\Actions\Episode\VideoFile\UpdateVideoFileCheckResultAction;
use App\Enums\VideoFileCheckErrorCode;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Once;

/**
 * Refreshes every episode's stored `has_video_file`, one chunk per job. Each job queues the next, so a slow drive
 * can't hit the worker's timeout and VLC tracking, which shares the worker, keeps running in between.
 */
class CheckEpisodeVideoFilesJob implements ShouldQueue
{
    use Queueable;

    // A check deleted as stale simply ends here.
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public VideoFileCheck $check,
        public int $afterId = 0,
    ) {}

    public function handle(CheckEpisodeVideoFilesAction $checkEpisodeVideoFilesAction): void
    {
        // queue:work is one long process: without this the base path memoized by an earlier job outlives a settings change
        Once::flush();

        $nextAfterId = $checkEpisodeVideoFilesAction->handle($this->check, $this->afterId);

        if ($nextAfterId !== null) {
            self::dispatch($this->check, $nextAfterId);
        }
    }

    /**
     * An error or the worker's timeout. Ends the check so it shows as failed; only a killed worker needs the stale check.
     */
    public function failed(): void
    {
        UpdateVideoFileCheckResultAction::run($this->check, VideoFileCheckErrorCode::Failed);
    }
}
