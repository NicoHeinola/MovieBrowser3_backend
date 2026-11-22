<?php

namespace App\Actions\VideoDurationScan;

use App\Dtos\VideoDurationScan\VideoDurationScanData;
use App\Models\Episode\Episode;
use App\Models\VideoDurationScan\VideoDurationScan;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

class GetVideoDurationScanAction
{
    use AsAction;

    /**
     * The running scan's progress, how the last finished one ended, and how many episodes with a file have no length.
     */
    public function handle(): VideoDurationScanData
    {
        $active = VideoDurationScan::query()->active()->first();
        $lastFinished = VideoDurationScan::query()->whereNotNull('finished_at')->latest('id')->first();

        return new VideoDurationScanData(
            running: $active !== null,
            read: $active->read ?? 0,
            total: $active->total ?? 0,
            finishedAt: $lastFinished?->finished_at,
            errorCode: $lastFinished?->error_code,
            // While a scan runs, the episodes it is about to read don't count as unknown yet
            unknownDurationsCount: Episode::query()
                ->unknownVideoDuration()
                ->when($active !== null, fn (Builder $query) => $query->whereNull('video_duration_queued_at'))
                ->count(),
        );
    }
}
