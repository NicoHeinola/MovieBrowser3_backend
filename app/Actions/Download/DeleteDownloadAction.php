<?php

namespace App\Actions\Download;

use App\Actions\Download\Client\IsDownloadGoneAction;
use App\Actions\Episode\DeleteEpisodeAction;
use App\Models\Download\Download;
use App\Models\Episode\Episode;
use App\Services\QBittorrent\QBittorrentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteDownloadAction
{
    use AsAction;

    public function __construct(
        private readonly QBittorrentService $qBittorrentService,
        private readonly DeleteEpisodeAction $deleteEpisodeAction,
        private readonly IsDownloadGoneAction $isDownloadGoneAction,
    ) {}

    /**
     * Cancels a download: removes the torrent and what it downloaded from qBittorrent, if it's still there. Its episodes
     * stay, missing their video. An episode that only waited for a better release keeps its video. An import that is copying
     * one of its files throws the copy away.
     *
     * @param  bool  $shouldRemoveWaitingEpisodes  Also deletes the episodes made for it: those without a video of their own
     *                                             whose file isn't in place yet
     */
    public function handle(Download $download, bool $shouldRemoveWaitingEpisodes = false): void
    {
        DB::transaction(function () use ($download, $shouldRemoveWaitingEpisodes): void {
            if ($shouldRemoveWaitingEpisodes) {
                $this->deleteWaitingEpisodes($download);
            }

            $download->delete();

            // Already gone: nothing to ask a qBittorrent that may be closed, and a torrent with this hash that someone
            // added there since is theirs
            if (!$this->isDownloadGoneAction->handle($download)) {
                // Last, so a failure keeps the download to cancel again
                $this->qBittorrentService->deleteTorrent($download->hash);
            }
        });
    }

    private function deleteWaitingEpisodes(Download $download): void
    {
        $waitingEpisodes = Episode::query()
            ->where('filename', '')
            ->whereHas('downloadPick', fn (Builder $pick) => $pick->where('download_id', $download->id)->whereNull('imported_at'))
            ->get();

        foreach ($waitingEpisodes as $waitingEpisode) {
            $this->deleteEpisodeAction->handle($waitingEpisode);
        }
    }
}
