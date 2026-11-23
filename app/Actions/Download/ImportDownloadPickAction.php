<?php

namespace App\Actions\Download;

use App\Actions\Episode\QueueEpisodeVideoDurationsAction;
use App\Actions\Episode\VideoFile\CheckVideoBasePathAction;
use App\Actions\Episode\VideoFile\CheckWindowsCopyAvailableAction;
use App\Actions\Episode\VideoFile\CopyVideoFileAction;
use App\Actions\Episode\VideoFile\DeleteVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Enums\DownloadErrorCode;
use App\Enums\DownloadStatus;
use App\Exceptions\NotEnoughDiskSpaceException;
use App\Models\DownloadPick\DownloadPick;
use App\Models\VideoRelocation\VideoRelocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ImportDownloadPickAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveDownloadPathAction $resolveDownloadPathAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly CheckVideoBasePathAction $checkVideoBasePathAction,
        private readonly CheckWindowsCopyAvailableAction $checkWindowsCopyAvailableAction,
        private readonly CopyVideoFileAction $copyVideoFileAction,
        private readonly DeleteVideoFileAction $deleteVideoFileAction,
        private readonly QueueEpisodeVideoDurationsAction $queueEpisodeVideoDurationsAction,
    ) {}

    /**
     * Copies a finished torrent file to its episode's video path, so the episode plays it. The torrent keeps its copy
     * to seed. A video the episode had (a better release was picked) is replaced once the copy commits. Safe to repeat:
     * an imported pick is skipped, and while a video folder move runs or the video drive is gone it waits for the next
     * sync. A failure marks the download failed with a code.
     */
    public function handle(DownloadPick $pick): void
    {
        $pick->refresh()->loadMissing('download', 'episode.entry.show.titles');

        if ($pick->imported_at !== null || $pick->download->status === DownloadStatus::Failed) {
            return;
        }

        if (VideoRelocation::query()->running()->exists() || !$this->checkVideoBasePathAction->handle()) {
            return;
        }

        $episode = $pick->episode;
        $sourcePath = $this->resolveDownloadPathAction->handle().'/'.$pick->path;
        $targetPath = $this->resolveEpisodeVideoPathAction->handle($episode, filename: basename($pick->path));
        $oldPath = $episode->filename === '' ? null : $this->resolveEpisodeVideoPathAction->handle($episode);

        if (!File::isFile($sourcePath)) {
            $this->fail($pick, DownloadErrorCode::FileMissing, "{$sourcePath} is not in the download folder");

            return;
        }

        $size = File::size($sourcePath);

        // The path comes from the episode, so a video it has with the same extension is in the way: it steps aside until
        // the copy is safely in place
        $asidePath = File::exists($targetPath) ? "{$targetPath}.replaced-".Str::random(8) : null;

        if ($asidePath !== null) {
            File::move($targetPath, $asidePath);
        }

        try {
            DB::transaction(function () use ($pick, $episode, $sourcePath, $targetPath, $oldPath, $asidePath, $size): void {
                $episode->update([
                    'filename' => basename($pick->path),
                    'has_video_file' => true,
                    'video_file_size' => $size,
                    // A replaced file's old length no longer applies; the scan queued below reads the new one
                    'video_duration_seconds' => null,
                    'video_duration_queued_at' => null,
                ]);
                $pick->update(['imported_at' => now()]);

                // Last, so a failed copy rolls the rows back (and the copy action removes its half-written file)
                $this->copyVideoFileAction->handle($sourcePath, $targetPath, $this->checkWindowsCopyAvailableAction->handle());

                DB::afterCommit(function () use ($episode, $oldPath, $targetPath, $asidePath): void {
                    if ($asidePath !== null) {
                        File::delete($asidePath);
                    }

                    if ($oldPath !== null && $oldPath !== $targetPath) {
                        $this->deleteVideoFileAction->handle($oldPath);
                    }

                    $this->queueEpisodeVideoDurationsAction->handle(episodeIds: [$episode->id]);
                });
            });
        } catch (Throwable $exception) {
            // The rollback removed the failed copy, so the video that stepped aside can go back
            if ($asidePath !== null) {
                File::move($asidePath, $targetPath);
            }

            $code = $exception instanceof NotEnoughDiskSpaceException ? DownloadErrorCode::NotEnoughDiskSpace : DownloadErrorCode::ImportFailed;
            $this->fail($pick, $code, $exception->getMessage());
        }
    }

    private function fail(DownloadPick $pick, DownloadErrorCode $code, string $reason): void
    {
        Log::warning("Importing a downloaded file failed ({$code->value}): {$reason}", ['download_pick_id' => $pick->id]);

        DB::transaction(fn () => $pick->download->update(['status' => DownloadStatus::Failed, 'error_code' => $code]));
    }
}
