<?php

namespace App\Actions\Download;

use App\Dtos\QBittorrent\QBittorrentTorrentData;
use App\Enums\DownloadErrorCode;
use App\Enums\DownloadStatus;
use App\Exceptions\DownloadClientNotConfiguredException;
use App\Exceptions\QBittorrentUnavailableException;
use App\Jobs\ImportDownloadPickJob;
use App\Models\Download\Download;
use App\Models\DownloadPick\DownloadPick;
use App\Services\QBittorrent\QBittorrentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncDownloadsAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
    ) {}

    /**
     * Brings every unfinished download in line with qBittorrent: queues the import of each picked file that finished,
     * marks a download completed once all its files are in place, and removes a completed torrent from qBittorrent once
     * qBittorrent stopped seeding it (its own share limit decides when). Runs on a schedule; a closed qBittorrent only
     * waits for the next run.
     */
    public function handle(): void
    {
        $downloads = Download::query()
            ->whereIn('status', [DownloadStatus::Downloading, DownloadStatus::Importing, DownloadStatus::Completed])
            ->whereNull('client_removed_at')
            ->with('picks')
            ->get();

        if ($downloads->isEmpty()) {
            return;
        }

        try {
            $torrents = $this->qBittorrentService->getTorrents($downloads->pluck('hash')->all());

            foreach ($downloads as $download) {
                $this->sync($download, $torrents[$download->hash] ?? null);
            }
        } catch (DownloadClientNotConfiguredException|QBittorrentUnavailableException $exception) {
            Log::info("Downloads not synced: {$exception->getMessage()}");
        }
    }

    private function sync(Download $download, ?QBittorrentTorrentData $torrent): void
    {
        // Gone from qBittorrent: after finishing, that's only an earlier cleanup; before, the files are lost
        if ($torrent === null) {
            DB::transaction(fn () => $download->status === DownloadStatus::Completed
                ? $download->update(['client_removed_at' => now()])
                : $download->update(['status' => DownloadStatus::Failed, 'error_code' => DownloadErrorCode::ClientLostTorrent]));

            return;
        }

        if ($download->status === DownloadStatus::Completed) {
            $this->removeOnceSeeded($download, $torrent);

            return;
        }

        $waiting = $download->picks->whereNull('imported_at');

        if ($waiting->isEmpty()) {
            DB::transaction(fn () => $download->update(['status' => DownloadStatus::Completed]));
            $this->removeOnceSeeded($download, $torrent);

            return;
        }

        $progressByIndex = collect($this->qBittorrentService->getFiles($download->hash) ?? [])->pluck('progress', 'index');
        $finished = $waiting->filter(fn (DownloadPick $pick): bool => $progressByIndex->get($pick->file_index, 0) >= 1);

        $status = $finished->isEmpty() ? DownloadStatus::Downloading : DownloadStatus::Importing;

        // Before the imports are queued: one can fail at once, and its failed status must not be overwritten
        if ($download->status !== $status) {
            DB::transaction(fn () => $download->update(['status' => $status]));
        }

        // Each job is unique per pick, so a copy still running isn't queued twice
        foreach ($finished as $pick) {
            ImportDownloadPickJob::dispatch($pick->id);
        }
    }

    /**
     * Every file is copied out, so the torrent only seeds. Once qBittorrent stopped it, it and its data go.
     */
    private function removeOnceSeeded(Download $download, QBittorrentTorrentData $torrent): void
    {
        if (!$torrent->isStoppedAfterFinishing()) {
            return;
        }

        DB::transaction(function () use ($download): void {
            $download->update(['client_removed_at' => now()]);

            // Last, so a failure keeps the download to clean up next time
            $this->qBittorrentService->deleteTorrent($download->hash, deleteFiles: true);
        });
    }
}
