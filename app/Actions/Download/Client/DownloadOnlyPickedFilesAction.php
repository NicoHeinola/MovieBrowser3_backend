<?php

namespace App\Actions\Download\Client;

use App\Enums\DownloadStatus;
use App\Exceptions\DownloadNotInClientException;
use App\Models\Download\Download;
use App\Services\QBittorrent\QBittorrentService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DownloadOnlyPickedFilesAction
{
    use AsAction;

    public function __construct(
        private readonly IsDownloadGoneAction $isDownloadGoneAction,
        private readonly QBittorrentService $qBittorrentService,
    ) {}

    /**
     * Tells qBittorrent which files to download: the files picked for episodes. It skips all other files. When no file
     * is picked, it stops the torrent.
     *
     * @throws DownloadNotInClientException When qBittorrent no longer has the torrent
     */
    public function handle(Download $download): void
    {
        if ($this->isDownloadGoneAction->handle($download)) {
            throw new DownloadNotInClientException;
        }

        $files = $this->qBittorrentService->getTorrentFiles($download->hash);

        if ($files === null) {
            throw new DownloadNotInClientException;
        }

        $pickedFileIndexes = $download->picks()->pluck('file_index')->all();
        $skippedFileIndexes = array_diff(array_column($files, 'index'), $pickedFileIndexes);
        $hasPickedFiles = $pickedFileIndexes !== [];

        DB::transaction(function () use ($download, $pickedFileIndexes, $skippedFileIndexes, $hasPickedFiles): void {
            $download->update([
                'status' => $hasPickedFiles ? DownloadStatus::Downloading : DownloadStatus::WaitingForFiles,
                // The download starts again, so the old error is not correct now
                'error_code' => null,
            ]);

            // These calls are last. If one fails, the status goes back to the old value
            $this->qBittorrentService->skipFiles($download->hash, array_values($skippedFileIndexes));
            $this->qBittorrentService->downloadFiles($download->hash, $pickedFileIndexes);

            if ($hasPickedFiles) {
                $this->qBittorrentService->startTorrent($download->hash);
            } else {
                $this->qBittorrentService->stopTorrent($download->hash);
            }
        });
    }
}
