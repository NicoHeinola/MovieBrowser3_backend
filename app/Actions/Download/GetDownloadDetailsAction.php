<?php

namespace App\Actions\Download;

use App\Dtos\Download\DownloadDetailsData;
use App\Dtos\Download\DownloadFileDetailsData;
use App\Dtos\QBittorrent\QBittorrentFileData;
use App\Models\Download\Download;
use App\Models\DownloadPick\DownloadPick;
use App\Services\QBittorrent\QBittorrentService;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDownloadDetailsAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
        private readonly ResolveDownloadDetailsAction $resolveDownloadDetailsAction,
    ) {}

    /**
     * The download with its torrent's files and progress as qBittorrent reports them now, and the episodes made for it.
     */
    public function handle(Download $download): DownloadDetailsData
    {
        $download->loadMissing('picks.episode.entry', 'show.titles')->loadCount(Download::getPickCounts());
        $torrent = $this->qBittorrentService->getTorrent($download->hash);

        $picksByFileIndex = $download->picks->keyBy('file_index');
        $files = $this->qBittorrentService->getTorrentFiles($download->hash) ?? [];
        $fileDetails = array_map(
            fn (QBittorrentFileData $file): DownloadFileDetailsData => $this->getFileDetails($file, $picksByFileIndex->get($file->index)),
            $files,
        );

        return $this->resolveDownloadDetailsAction->handle($download, $torrent, $fileDetails);
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
