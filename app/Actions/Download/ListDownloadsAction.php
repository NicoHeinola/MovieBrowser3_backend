<?php

namespace App\Actions\Download;

use App\Dtos\Download\DownloadDetailsData;
use App\Models\Download\Download;
use App\Services\QBittorrent\QBittorrentService;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

class ListDownloadsAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
        private readonly GetDownloadFileDetailsAction $getDownloadFileDetailsAction,
        private readonly ResolveDownloadDetailsAction $resolveDownloadDetailsAction,
    ) {}

    /**
     * The downloads, newest first, with progress as qBittorrent reports it now (one request for all of them), and how
     * many of their files are picked, imported and waited for.
     *
     * @param  list<int>|null  $ids  Only these downloads; null for all of them. Ids that don't exist are left out
     * @param  bool  $withFiles  Also read each torrent's files, one request per download
     * @return list<DownloadDetailsData>
     */
    public function handle(?array $ids = null, bool $withFiles = false): array
    {
        $downloads = Download::query()
            ->when($ids !== null, fn (Builder $query) => $query->whereKey($ids))
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
                $withFiles ? $this->getDownloadFileDetailsAction->handle($download) : null,
            ))
            ->all();
    }
}
