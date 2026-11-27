<?php

namespace App\Jobs;

use App\Actions\Download\Client\RemoveSeededTorrentsAction;
use App\Actions\Download\Client\SyncUnfinishedDownloadsAction;
use App\Exceptions\DownloadClientNotConfiguredException;
use App\Exceptions\QBittorrentUnavailableException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Brings downloads in line with qBittorrent; scheduled every minute. A closed qBittorrent only waits for the next run.
 */
class SyncDownloadsJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        SyncUnfinishedDownloadsAction $syncUnfinishedDownloadsAction,
        RemoveSeededTorrentsAction $removeSeededTorrentsAction,
    ): void {
        try {
            $syncUnfinishedDownloadsAction->handle();
            $removeSeededTorrentsAction->handle();
        } catch (DownloadClientNotConfiguredException|QBittorrentUnavailableException $exception) {
            Log::info("Downloads not synced: {$exception->getMessage()}");
        }
    }
}
