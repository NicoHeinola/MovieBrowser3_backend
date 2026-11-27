<?php

namespace App\Actions\Download\Client;

use App\Enums\DownloadStatus;
use App\Models\Download\Download;
use App\Services\QBittorrent\QBittorrentService;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveSeededTorrentsAction
{
    use AsAction;

    public function __construct(private readonly QBittorrentService $qBittorrentService) {}

    /**
     * Removes every completed torrent from qBittorrent, files and all, once qBittorrent stopped seeding it: its own share
     * limit decides when. Its episodes have their own copies.
     */
    public function handle(): void
    {
        $downloads = Download::query()->where('status', DownloadStatus::Completed)->whereNull('client_removed_at')->get();
        $torrents = $this->qBittorrentService->getTorrents($downloads->pluck('hash')->all());

        foreach ($downloads as $download) {
            $torrent = $torrents[$download->hash] ?? null;
            $isSeeding = $torrent !== null && !$torrent->isStoppedAfterFinishing();

            if ($isSeeding) {
                continue;
            }

            // Someone may have removed it there already, which is fine
            $this->qBittorrentService->deleteTorrent($download->hash);
            $download->update(['client_removed_at' => now()]);
        }
    }
}
