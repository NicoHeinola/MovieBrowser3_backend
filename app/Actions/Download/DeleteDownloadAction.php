<?php

namespace App\Actions\Download;

use App\Actions\Episode\DeleteEpisodeAction;
use App\Enums\DownloadStatus;
use App\Exceptions\DownloadImportRunningException;
use App\Models\Download\Download;
use App\Models\Episode\Episode;
use App\Services\QBittorrent\QBittorrentService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteDownloadAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
        private readonly DeleteEpisodeAction $deleteEpisodeAction,
        private readonly ResolveDownloadGoneAction $resolveDownloadGoneAction,
    ) {}

    /**
     * Cancels a download: removes the torrent and what it downloaded from qBittorrent, if it's still there. Its episodes stay, missing their
     * video, unless `$removeWaitingEpisodes` asks to remove the ones made for it: no video of their own, file not in
     * place yet. An episode that only waited for a better release keeps its video either way. Refused while its files
     * are copied into their episodes.
     *
     * @throws DownloadImportRunningException
     */
    public function handle(Download $download, bool $removeWaitingEpisodes = false): void
    {
        // A copy holds its episode's row until it's in place: a cancel would wait for it, then delete the episode
        // without the video it just got
        if ($download->status === DownloadStatus::Importing) {
            throw new DownloadImportRunningException;
        }

        DB::transaction(function () use ($download, $removeWaitingEpisodes): void {
            if ($removeWaitingEpisodes) {
                $episodes = Episode::query()
                    ->where('filename', '')
                    ->whereHas('downloadPick', fn ($pick) => $pick->where('download_id', $download->id)->whereNull('imported_at'))
                    ->get();

                foreach ($episodes as $episode) {
                    $this->deleteEpisodeAction->handle($episode);
                }
            }

            $download->delete();

            // Already gone: nothing to ask a qBittorrent that may be closed, and a torrent with this hash that someone
            // added there since is theirs
            if ($this->resolveDownloadGoneAction->handle($download)) {
                return;
            }

            // Last, so a failure keeps the download to cancel again
            $this->qBittorrentService->deleteTorrent($download->hash, deleteFiles: true);
        });
    }
}
