<?php

namespace App\Actions\Download;

use App\Dtos\Download\DownloadDetailsData;
use App\Models\Download\Download;
use App\Services\QBittorrent\QBittorrentService;
use Lorisleiva\Actions\Concerns\AsAction;

class ListDownloadsAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
        private readonly ResolveDownloadDetailsAction $resolveDownloadDetailsAction,
    ) {}

    /**
     * Every download, newest first, with progress as qBittorrent reports it now (one request for all of them). Files
     * aren't read; how many of them are picked, imported and waited for is.
     *
     * @return list<DownloadDetailsData>
     */
    public function handle(): array
    {
        $downloads = Download::query()
            ->with('show.titles')
            ->withCount(Download::getPickCounts())
            ->latest()
            ->orderByDesc('id')
            ->get();

        $torrents = $this->qBittorrentService->getTorrents($downloads->pluck('hash')->all());

        return $downloads
            ->map(fn (Download $download): DownloadDetailsData => $this->resolveDownloadDetailsAction->handle(
                $download,
                $torrents[$download->hash] ?? null,
            ))
            ->all();
    }
}
