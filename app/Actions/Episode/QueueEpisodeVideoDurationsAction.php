<?php

namespace App\Actions\Episode;

use App\Actions\VideoDurationScan\CreateVideoDurationScanAction;
use App\Exceptions\VideoRelocationRunningException;
use App\Models\Episode\Episode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class QueueEpisodeVideoDurationsAction
{
    use AsAction;

    public function __construct(private readonly CreateVideoDurationScanAction $createVideoDurationScanAction) {}

    /**
     * Queues episodes with a file to have their video's length read, then starts a scan for the queue. Without limits
     * it queues every episode with a file. Touches no disk: reading happens in the scan's job, which may be slow on a
     * Windows drive.
     *
     * @param  list<int>|null  $showIds  limits it to these shows' episodes
     * @param  list<int>|null  $episodeIds  limits it to these episodes
     * @param  bool  $onlyMissing  limits it to episodes without a length: never read, unreadable, or left waiting
     *
     * @throws VideoRelocationRunningException files on the move can't be read; nothing is queued then
     */
    public function handle(?array $showIds = null, ?array $episodeIds = null, bool $onlyMissing = false): void
    {
        DB::transaction(function () use ($showIds, $episodeIds, $onlyMissing): void {
            Episode::query()
                ->where('has_video_file', true)
                ->where('filename', '!=', '')
                ->when($showIds !== null, fn (Builder $query) => $query->whereHas('entry', fn (Builder $entries) => $entries->whereIn('show_id', $showIds)))
                ->when($episodeIds !== null, fn (Builder $query) => $query->whereKey($episodeIds))
                ->when($onlyMissing, fn (Builder $query) => $query->whereNull('video_duration_seconds'))
                ->update(['video_duration_queued_at' => now()]);

            // In the same transaction, so a refused start (a folder move) leaves nothing queued
            $this->createVideoDurationScanAction->handle();
        });
    }
}
