<?php

namespace App\Jobs;

use App\Actions\Download\SyncDownloadsAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Brings downloads in line with qBittorrent; scheduled every minute. One at a time: a slow qBittorrent must not pile
 * up runs.
 */
class SyncDownloadsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(SyncDownloadsAction $action): void
    {
        $action->handle();
    }
}
