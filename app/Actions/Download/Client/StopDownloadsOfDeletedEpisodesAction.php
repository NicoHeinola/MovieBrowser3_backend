<?php

namespace App\Actions\Download\Client;

use App\Exceptions\DownloadClientNotConfiguredException;
use App\Exceptions\DownloadNotInClientException;
use App\Exceptions\QBittorrentUnavailableException;
use App\Models\Download\Download;
use App\Models\DownloadPick\DownloadPick;
use App\Models\Episode\Episode;
use App\Services\QBittorrent\QBittorrentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class StopDownloadsOfDeletedEpisodesAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
        private readonly DownloadOnlyPickedFilesAction $downloadOnlyPickedFilesAction,
        private readonly IsDownloadGoneAction $isDownloadGoneAction,
    ) {}

    /**
     * Stops the downloads of files that were picked for these episodes. It does this after the deletion is saved,
     * because the picks are deleted with the episodes. When no show needs a download, it removes the torrent and its
     * files from qBittorrent.
     *
     * @param  iterable<Episode>  $episodes  The episodes that are being deleted
     */
    public function handle(iterable $episodes): void
    {
        $episodeIds = collect($episodes)->map(fn (Episode $episode): int => $episode->id)->all();
        $downloadIds = DownloadPick::query()->whereIn('episode_id', $episodeIds)->distinct()->pluck('download_id')->all();

        DB::afterCommit(function () use ($downloadIds): void {
            $downloads = Download::query()->whereKey($downloadIds)->withCount('picks')->get();

            foreach ($downloads as $download) {
                $this->stopDownload($download);
            }
        });
    }

    /**
     * qBittorrent can be closed. The episodes are already deleted and the deletion must not fail, so an error only
     * writes a log entry.
     */
    private function stopDownload(Download $download): void
    {
        // Its show is deleted and no episode has a file from it. Nobody can pick its files again
        $isNeededByNobody = $download->picks_count === 0 && $download->show_id === null;

        try {
            if ($isNeededByNobody) {
                $this->deleteDownload($download);
            } else {
                $this->downloadOnlyPickedFilesAction->handle($download);
            }
        } catch (QBittorrentUnavailableException|DownloadClientNotConfiguredException|DownloadNotInClientException $exception) {
            Log::warning('A download of deleted episodes could not be updated in qBittorrent.', [
                'download_id' => $download->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    private function deleteDownload(Download $download): void
    {
        DB::transaction(function () use ($download): void {
            $download->delete();

            // Someone can add the same torrent again in qBittorrent. Then it is theirs, so do not delete it
            if (!$this->isDownloadGoneAction->handle($download)) {
                $this->qBittorrentService->deleteTorrent($download->hash);
            }
        });
    }
}
