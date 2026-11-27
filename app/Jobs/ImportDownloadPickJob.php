<?php

namespace App\Jobs;

use App\Actions\Download\Import\ImportDownloadPickAction;
use App\Models\DownloadPick\DownloadPick;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gives one finished torrent file to its episode. Unique per pick, so the sync can queue it every minute without queueing
 * it twice; a pick whose episode or download was deleted meanwhile is skipped. Runs on a queue and worker of its own:
 * copies take minutes, and VLC tracking, the sync and the video file checks must not wait for them. Exactly one worker: two could
 * copy the same file after one was killed.
 */
class ImportDownloadPickJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'imports';

    // A copy between disks can outlast the worker's default 60 second timeout; 0 turns it off for this job
    public int $timeout = 0;

    // Outlives a long queue. A killed worker's job comes back, fails on the attempt limit and lets go of it earlier
    public int $uniqueFor = 6 * 60 * 60;

    // A pick deleted meanwhile has nothing left to import.
    public bool $deleteWhenMissingModels = true;

    public function __construct(public DownloadPick $pick)
    {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return (string) $this->pick->id;
    }

    public function handle(ImportDownloadPickAction $action): void
    {
        $action->handle($this->pick);
    }
}
