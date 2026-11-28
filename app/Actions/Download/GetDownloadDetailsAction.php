<?php

namespace App\Actions\Download;

use App\Dtos\Download\DownloadDetailsData;
use App\Models\Download\Download;
use App\Services\QBittorrent\QBittorrentService;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDownloadDetailsAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
        private readonly GetDownloadFileDetailsAction $getDownloadFileDetailsAction,
        private readonly ResolveDownloadDetailsAction $resolveDownloadDetailsAction,
    ) {}

    /**
     * The download with its torrent's files and progress as qBittorrent reports them now, and the episodes made for it.
     */
    public function handle(Download $download): DownloadDetailsData
    {
        $download->loadMissing('show.titles')->loadCount(Download::getPickCounts());
        $torrent = $this->qBittorrentService->getTorrent($download->hash);

        return $this->resolveDownloadDetailsAction->handle(
            $download,
            $torrent,
            $this->getDownloadFileDetailsAction->handle($download),
        );
    }
}
