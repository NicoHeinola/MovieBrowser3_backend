<?php

namespace App\Actions\Download;

use App\Actions\Download\Client\DownloadOnlyPickedFilesAction;
use App\Actions\Episode\VideoFile\Path\IsVideoBaseFolderAvailableAction;
use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Enums\DownloadStatus;
use App\Exceptions\DownloadNotInClientException;
use App\Models\Download\Download;
use App\Services\QBittorrent\QBittorrentService;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class RetryDownloadAction
{
    use AsAction;

    public function __construct(
        private readonly IsVideoBaseFolderAvailableAction $isVideoBaseFolderAvailableAction,
        private readonly QBittorrentService $qBittorrentService,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly DownloadOnlyPickedFilesAction $downloadOnlyPickedFilesAction,
    ) {}

    /**
     * Goes over a download again, going by the files that are really there: imported files whose video is gone wait
     * again, and qBittorrent checks the torrent's files and downloads what's missing. The sync imports them afterwards.
     *
     * @throws DownloadNotInClientException When qBittorrent no longer has the torrent: it's added again instead
     */
    public function handle(Download $download): Download
    {
        $this->forgetMissingVideos($download);

        $hasPicksToImport = $download->picks()->whereNull('imported_at')->exists();
        $hasFailed = $download->status === DownloadStatus::Failed;

        // Every picked file is its episode's video, so there's nothing to go over
        if (!$hasPicksToImport && !$hasFailed) {
            return $download;
        }

        $this->downloadOnlyPickedFilesAction->handle($download);
        $this->qBittorrentService->recheckTorrent($download->hash);

        return $download->fresh();
    }

    /**
     * An unmounted drive makes every video look missing, so only while the video base folder can be trusted.
     */
    private function forgetMissingVideos(Download $download): void
    {
        if (!$this->isVideoBaseFolderAvailableAction->handle()) {
            return;
        }

        $importedPicks = $download->picks()->whereNotNull('imported_at')->with('episode.entry.show.titles')->get();

        foreach ($importedPicks as $importedPick) {
            $videoPath = $this->resolveEpisodeVideoPathAction->handle($importedPick->episode);

            if (!File::isFile($videoPath)) {
                $importedPick->update(['imported_at' => null]);
            }
        }
    }
}
