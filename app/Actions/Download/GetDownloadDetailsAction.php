<?php

namespace App\Actions\Download;

use App\Dtos\Download\DownloadDetailsData;
use App\Dtos\Download\DownloadFileDetailsData;
use App\Dtos\QBittorrent\QBittorrentFileData;
use App\Enums\DownloadErrorCode;
use App\Enums\DownloadStatus;
use App\Models\Download\Download;
use App\Services\QBittorrent\QBittorrentService;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDownloadDetailsAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
    ) {}

    /**
     * The download with its torrent's files and progress as qBittorrent reports them now. Reads only: a torrent
     * qBittorrent lost is reported as failed, not stored as failed.
     */
    public function handle(Download $download): DownloadDetailsData
    {
        $download->loadMissing('picks.episode.entry', 'show.titles');
        $torrent = $this->qBittorrentService->getTorrent($download->hash);

        if ($torrent === null) {
            return new DownloadDetailsData($download, DownloadStatus::Failed, DownloadErrorCode::ClientLostTorrent, null, []);
        }

        $picks = $download->picks->keyBy('file_index');
        $files = array_map(fn (QBittorrentFileData $file): DownloadFileDetailsData => new DownloadFileDetailsData(
            index: $file->index,
            path: $file->path,
            sizeBytes: $file->sizeBytes,
            progress: $file->progress,
            episodeId: $picks->get($file->index)?->episode_id,
            showId: $picks->get($file->index)?->episode?->entry?->show_id,
        ), $this->qBittorrentService->getFiles($download->hash) ?? []);

        // A magnet's files come from peers first, so a waiting download may not have any to pick yet
        $status = $download->status === DownloadStatus::WaitingForFiles && !$torrent->hasMetadata
            ? DownloadStatus::FetchingMetadata
            : $download->status;

        return new DownloadDetailsData($download, $status, $download->error_code, $torrent, $files);
    }
}
