<?php

namespace App\Actions\Download\Import;

use App\Actions\Download\Client\ResolveDownloadFolderAction;
use App\Actions\Episode\VideoFile\DeleteVideoFileAction;
use App\Actions\Episode\VideoFile\Path\IsVideoBaseFolderAvailableAction;
use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\ReplaceEpisodeVideoAction;
use App\Actions\File\CopyFileAction;
use App\Enums\DownloadErrorCode;
use App\Enums\DownloadStatus;
use App\Exceptions\DownloadFileMissingException;
use App\Exceptions\NotEnoughDiskSpaceException;
use App\Models\DownloadPick\DownloadPick;
use App\Models\Episode\Episode;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ImportDownloadPickAction
{
    use AsAction;

    public function __construct(
        private readonly IsVideoBaseFolderAvailableAction $isVideoBaseFolderAvailableAction,
        private readonly CopyFileAction $copyFileAction,
        private readonly DeleteVideoFileAction $deleteVideoFileAction,
        private readonly ReplaceEpisodeVideoAction $replaceEpisodeVideoAction,
        private readonly ResolveDownloadFolderAction $resolveDownloadFolderAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    /**
     * Gives a finished torrent file to its episode, so the episode plays it. The torrent keeps its own to seed. A video
     * the episode had (a better release was picked) stays until the new one is complete. While a video base folder move
     * runs or the video drive is gone it waits for the next sync. A failure marks the download failed with a code; its
     * other files still import. Safe to repeat, and to kill at any point.
     */
    public function handle(DownloadPick $pick): void
    {
        if (!$this->isVideoBaseFolderAvailableAction->handle()) {
            return;
        }

        // Another job of it may have been first
        if ($pick->imported_at !== null) {
            return;
        }

        try {
            $this->importPick($pick);
        } catch (Throwable $exception) {
            $this->markDownloadFailed($pick, $exception);
        }
    }

    private function importPick(DownloadPick $pick): void
    {
        $sourcePath = $this->resolveDownloadFolderAction->handle().'/'.$pick->path;

        if (!File::isFile($sourcePath)) {
            throw new DownloadFileMissingException($sourcePath);
        }

        $videoPath = $this->resolveEpisodeVideoPathAction->handle($pick->episode, filename: basename($pick->path));
        // Under a name of its own next to where it goes, so the old video stays until the new one is complete
        $copyPath = dirname($videoPath)."/{$pick->episode_id}.importing";

        // Left behind by an import that was killed
        File::delete($copyPath);

        $this->copyFileAction->handle($sourcePath, $copyPath);
        $this->giveVideoToEpisode($pick, $copyPath);

        // Deletes the copy when the episode no longer wants it, and a folder the episode left during the copy
        $this->deleteVideoFileAction->handle($copyPath);
    }

    /**
     * Everything is read again: the copy took a while, and the episode may have been renamed, moved or deleted
     * meanwhile.
     */
    private function giveVideoToEpisode(DownloadPick $pick, string $copyPath): void
    {
        // It would leave the video behind. The next sync imports it into the new folder
        if (VideoBaseFolderMove::query()->running()->exists()) {
            return;
        }

        DB::transaction(function () use ($pick, $copyPath): void {
            // The episode before its pick, the order in which deleting an episode locks them, or the two could wait for
            // each other. Not `for update`, which would also wait for a pick being added for the episode
            $episode = Episode::query()->with('entry.show.titles')->lock('for no key update')->find($pick->episode_id);
            $isWaiting = DownloadPick::query()->whereKey($pick->id)->whereNull('imported_at')->lockForUpdate()->exists();

            if ($episode === null || !$isWaiting) {
                return;
            }

            DownloadPick::query()->whereKey($pick->id)->update(['imported_at' => now()]);

            // Last, because it renames the file
            $this->replaceEpisodeVideoAction->handle($episode, $copyPath, basename($pick->path));
        });
    }

    private function markDownloadFailed(DownloadPick $pick, Throwable $exception): void
    {
        $errorCode = match (true) {
            $exception instanceof DownloadFileMissingException => DownloadErrorCode::FileMissing,
            $exception instanceof NotEnoughDiskSpaceException => DownloadErrorCode::NotEnoughDiskSpace,
            default => DownloadErrorCode::ImportFailed,
        };

        Log::warning("Importing a downloaded file failed ({$errorCode->value}): {$exception->getMessage()}", ['download_pick_id' => $pick->id]);

        $pick->download->update(['status' => DownloadStatus::Failed, 'error_code' => $errorCode]);
    }
}
