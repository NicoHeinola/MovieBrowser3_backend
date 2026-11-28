<?php

namespace App\Actions\Download;

use App\Dtos\Download\DownloadFileDetailsData;
use App\Dtos\QBittorrent\QBittorrentFileData;
use App\Models\Download\Download;
use App\Models\DownloadPick\DownloadPick;
use App\Services\QBittorrent\QBittorrentService;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDownloadFileDetailsAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
    ) {}

    /**
     * The torrent's files as qBittorrent reports them now, each with the episode picked for it. Empty while a magnet's
     * file list is still being fetched, and when qBittorrent doesn't have the torrent.
     *
     * @return list<DownloadFileDetailsData>
     */
    public function handle(Download $download): array
    {
        $download->loadMissing('picks.episode.entry');

        $picksByFileIndex = $download->picks->keyBy('file_index');
        $files = $this->qBittorrentService->getTorrentFiles($download->hash) ?? [];

        return array_map(
            fn (QBittorrentFileData $file): DownloadFileDetailsData => $this->getFileDetails($file, $picksByFileIndex->get($file->index)),
            $files,
        );
    }

    private function getFileDetails(QBittorrentFileData $file, ?DownloadPick $pick): DownloadFileDetailsData
    {
        return new DownloadFileDetailsData(
            index: $file->index,
            path: $file->path,
            sizeBytes: $file->sizeBytes,
            progress: $file->progress,
            episodeId: $pick?->episode_id,
            showId: $pick?->episode?->entry?->show_id,
            importedAt: $pick?->imported_at,
        );
    }
}
