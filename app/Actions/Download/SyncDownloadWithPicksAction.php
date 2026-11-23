<?php

namespace App\Actions\Download;

use App\Dtos\QBittorrent\QBittorrentFileData;
use App\Enums\DownloadStatus;
use App\Exceptions\DownloadNotInClientException;
use App\Models\Download\Download;
use App\Services\QBittorrent\QBittorrentService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncDownloadWithPicksAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
    ) {}

    /**
     * Has qBittorrent download exactly the files picked for episodes, as stored. With none picked the torrent stops
     * and waits.
     *
     * @throws DownloadNotInClientException When qBittorrent no longer has the torrent
     */
    public function handle(Download $download): void
    {
        $picked = $download->picks()->pluck('file_index')->all();
        $torrentFiles = $this->qBittorrentService->getFiles($download->hash) ?? throw new DownloadNotInClientException;
        $skipped = array_values(array_diff(array_map(fn (QBittorrentFileData $file): int => $file->index, $torrentFiles), $picked));

        DB::transaction(function () use ($download, $picked, $skipped): void {
            $download->update([
                'status' => $picked === [] ? DownloadStatus::WaitingForFiles : DownloadStatus::Downloading,
                // It starts over, so why it failed before no longer applies
                'error_code' => null,
            ]);

            // Last, so a failure rolls the status back
            $this->qBittorrentService->setFilePriority($download->hash, $skipped, 0);
            $this->qBittorrentService->setFilePriority($download->hash, $picked, 1);

            if ($picked === []) {
                $this->qBittorrentService->stop($download->hash);
            } else {
                $this->qBittorrentService->start($download->hash);
            }
        });
    }
}
