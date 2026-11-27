<?php

namespace App\Actions\Download\Client;

use App\Dtos\QBittorrent\QBittorrentTorrentData;
use App\Enums\DownloadErrorCode;
use App\Enums\DownloadStatus;
use App\Jobs\ImportDownloadPickJob;
use App\Models\Download\Download;
use App\Models\DownloadPick\DownloadPick;
use App\Services\QBittorrent\QBittorrentService;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncUnfinishedDownloadsAction
{
    use AsAction;

    public function __construct(private readonly QBittorrentService $qBittorrentService) {}

    /**
     * Moves every unfinished download forward to where qBittorrent is: lost, completed (every picked file is imported), or
     * with some files finished, whose imports are queued.
     */
    public function handle(): void
    {
        $downloads = Download::query()
            ->whereIn('status', [DownloadStatus::Downloading, DownloadStatus::Importing])
            ->with('picks')
            ->get();

        $torrents = $this->qBittorrentService->getTorrents($downloads->pluck('hash')->all());

        foreach ($downloads as $download) {
            $this->syncDownload($download, $torrents[$download->hash] ?? null);
        }
    }

    private function syncDownload(Download $download, ?QBittorrentTorrentData $torrent): void
    {
        if ($torrent === null) {
            $download->update(['status' => DownloadStatus::Failed, 'error_code' => DownloadErrorCode::ClientLostTorrent]);

            return;
        }

        // Until the check is done, qBittorrent may still call missing files finished
        if ($torrent->isChecking()) {
            return;
        }

        $picksToImport = $download->picks->whereNull('imported_at');

        // Every picked file already is its episode's video
        if ($picksToImport->isEmpty()) {
            $download->update(['status' => DownloadStatus::Completed]);

            return;
        }

        $this->importFinishedFiles($download, $picksToImport);
    }

    /**
     * @param  Collection<int, DownloadPick>  $picksToImport
     */
    private function importFinishedFiles(Download $download, Collection $picksToImport): void
    {
        $files = collect($this->qBittorrentService->getTorrentFiles($download->hash));
        $finishedFileIndexes = $files->where('progress', '>=', 1)->pluck('index');
        $finishedPicks = $picksToImport->whereIn('file_index', $finishedFileIndexes);

        // Before the imports are queued: one can fail at once, and its failed status must not be overwritten
        $status = $finishedPicks->isEmpty() ? DownloadStatus::Downloading : DownloadStatus::Importing;
        $download->update(['status' => $status]);

        // Each job is unique per pick, so one that is still running or waiting isn't queued twice
        foreach ($finishedPicks as $finishedPick) {
            ImportDownloadPickJob::dispatch($finishedPick);
        }
    }
}
