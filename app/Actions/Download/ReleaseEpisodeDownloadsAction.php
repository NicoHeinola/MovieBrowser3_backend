<?php

namespace App\Actions\Download;

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

class ReleaseEpisodeDownloadsAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
        private readonly SyncDownloadWithPicksAction $syncDownloadWithPicksAction,
    ) {}

    /**
     * Stops downloading the torrent files picked for episodes being deleted, once the deletion commits (their picks go
     * with them). A download left with no picks and no show (the show was deleted) is removed from qBittorrent with its
     * files, since nothing will pick them again.
     *
     * @param  iterable<Episode>  $episodes
     */
    public function handle(iterable $episodes): void
    {
        $episodeIds = collect($episodes)->map(fn (Episode $episode): int => $episode->id)->all();
        $downloadIds = DownloadPick::query()->whereIn('episode_id', $episodeIds)->distinct()->pluck('download_id')->all();

        if ($downloadIds === []) {
            return;
        }

        DB::afterCommit(function () use ($downloadIds): void {
            foreach (Download::query()->whereKey($downloadIds)->withCount('picks')->get() as $download) {
                $this->release($download);
            }
        });
    }

    /**
     * qBittorrent may be closed: the deletion has happened and must not fail for it, so this only logs.
     */
    private function release(Download $download): void
    {
        try {
            if ($download->picks_count === 0 && $download->show_id === null) {
                DB::transaction(function () use ($download): void {
                    $download->delete();

                    $this->qBittorrentService->deleteTorrent($download->hash, deleteFiles: true);
                });

                return;
            }

            $this->syncDownloadWithPicksAction->handle($download);
        } catch (QBittorrentUnavailableException|DownloadClientNotConfiguredException|DownloadNotInClientException $exception) {
            Log::warning('A download of deleted episodes could not be updated in qBittorrent.', [
                'download_id' => $download->id,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
