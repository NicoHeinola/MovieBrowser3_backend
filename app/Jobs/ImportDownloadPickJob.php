<?php

namespace App\Jobs;

use App\Actions\Download\ImportDownloadPickAction;
use App\Models\DownloadPick\DownloadPick;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Copies one finished torrent file into its episode. Unique per pick, so the sync can queue it every minute without
 * queueing a copy twice; a pick whose episode or download was deleted meanwhile is skipped.
 */
class ImportDownloadPickJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    // A copy between disks can outlast the worker's default 60 second timeout; 0 turns it off for this job
    public int $timeout = 0;

    // The lock outlives a long copy, but not a worker that died mid-copy: the next sync can queue the pick again
    public int $uniqueFor = 6 * 60 * 60;

    public function __construct(public readonly int $pickId) {}

    public function uniqueId(): string
    {
        return (string) $this->pickId;
    }

    public function handle(ImportDownloadPickAction $action): void
    {
        $pick = DownloadPick::query()->find($this->pickId);

        if ($pick !== null) {
            $action->handle($pick);
        }
    }
}
