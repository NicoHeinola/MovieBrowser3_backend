<?php

namespace App\Actions\Download;

use App\Dtos\Download\DownloadDetailsData;
use App\Dtos\Download\DownloadFileDetailsData;
use App\Dtos\QBittorrent\QBittorrentTorrentData;
use App\Enums\DownloadErrorCode;
use App\Enums\DownloadStatus;
use App\Models\Download\Download;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveDownloadDetailsAction
{
    use AsAction;

    /**
     * A download as qBittorrent's answer shows it. Reads only: a torrent qBittorrent lost is reported as failed, not
     * stored as failed, and a waiting magnet whose files aren't fetched yet is reported as fetching them.
     *
     * @param  QBittorrentTorrentData|null  $torrent  Null when qBittorrent doesn't have it
     * @param  list<DownloadFileDetailsData>|null  $files  Null when they weren't read
     */
    public function handle(Download $download, ?QBittorrentTorrentData $torrent, ?array $files = null, ?int $waitingEpisodeCount = null): DownloadDetailsData
    {
        // A completed download's torrent leaves qBittorrent on purpose once it's done seeding
        if ($torrent === null && $download->status !== DownloadStatus::Completed) {
            return new DownloadDetailsData($download, DownloadStatus::Failed, DownloadErrorCode::ClientLostTorrent, null, $files === null ? null : [], $waitingEpisodeCount);
        }

        $status = $download->status === DownloadStatus::WaitingForFiles && $torrent?->hasMetadata === false
            ? DownloadStatus::FetchingMetadata
            : $download->status;

        return new DownloadDetailsData($download, $status, $download->error_code, $torrent, $files, $waitingEpisodeCount);
    }
}
