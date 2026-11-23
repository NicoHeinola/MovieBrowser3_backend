<?php

namespace App\Actions\Download;

use App\Actions\Episode\DeleteEpisodeAction;
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
    ) {}

    /**
     * Cancels a download: removes the torrent and what it downloaded from qBittorrent. Its episodes stay, missing their
     * video, unless `$removeWaitingEpisodes` asks to remove the ones made for it: no video of their own, file not in
     * place yet. An episode that only waited for a better release keeps its video either way.
     */
    public function handle(Download $download, bool $removeWaitingEpisodes = false): void
    {
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

            // Last, so a failure keeps the download to cancel again
            $this->qBittorrentService->deleteTorrent($download->hash, deleteFiles: true);
        });
    }
}
