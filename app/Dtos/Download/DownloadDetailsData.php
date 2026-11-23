<?php

namespace App\Dtos\Download;

use App\Dtos\QBittorrent\QBittorrentTorrentData;
use App\Enums\DownloadErrorCode;
use App\Enums\DownloadStatus;
use App\Models\Download\Download;
use Spatie\LaravelData\Data;

class DownloadDetailsData extends Data
{
    /**
     * A download with what qBittorrent reports about it right now.
     *
     * @param  DownloadStatus  $status  The stored status, or what qBittorrent's answer shows it to be
     * @param  QBittorrentTorrentData|null  $torrent  Null when qBittorrent no longer has the torrent
     * @param  list<DownloadFileDetailsData>|null  $files  Every file of the torrent, empty until qBittorrent knows them;
     *                                                     null when not read, as in the list of downloads
     * @param  int|null  $waitingEpisodeCount  Episodes that have no video and wait only for this download; null when
     *                                         not counted
     */
    public function __construct(
        public Download $download,
        public DownloadStatus $status,
        public ?DownloadErrorCode $errorCode,
        public ?QBittorrentTorrentData $torrent,
        public ?array $files,
        public ?int $waitingEpisodeCount = null,
    ) {}
}
