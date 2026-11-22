<?php

namespace App\Jobs;

use App\Actions\Episode\VideoFile\ReadEpisodeVideoDurationsAction;
use App\Actions\VideoDurationScan\UpdateVideoDurationScanResultAction;
use App\Enums\VideoDurationScanErrorCode;
use App\Models\VideoDurationScan\VideoDurationScan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Once;

/**
 * Reads the queued episodes' video lengths, one time-boxed chunk per job. Each job queues the next, so a slow drive
 * can't hit the worker's timeout and VLC tracking, which shares the worker, keeps running in between.
 */
class ReadEpisodeVideoDurationsJob implements ShouldQueue
{
    use Queueable;

    // A scan deleted as stale simply ends here.
    public bool $deleteWhenMissingModels = true;

    public function __construct(public VideoDurationScan $scan) {}

    public function handle(ReadEpisodeVideoDurationsAction $readEpisodeVideoDurationsAction): void
    {
        // queue:work is one long process: without this the base path memoized by an earlier job outlives a settings change
        Once::flush();

        if ($readEpisodeVideoDurationsAction->handle($this->scan)) {
            self::dispatch($this->scan);
        }
    }

    /**
     * An error or the worker's timeout. Ends the scan so it shows as failed; its episodes stay queued for the next one.
     */
    public function failed(): void
    {
        UpdateVideoDurationScanResultAction::run($this->scan, VideoDurationScanErrorCode::Failed);
    }
}
