<?php

namespace App\Actions\Episode\VideoFile;

use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Models\Episode\Episode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class ReplaceEpisodeVideoAction
{
    use AsAction;

    public function __construct(
        private readonly DeleteVideoFileAction $deleteVideoFileAction,
        private readonly ReadVideoFileDurationAction $readVideoFileDurationAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    /**
     * Makes a complete file the episode's video. The file replaces the video that the episode had.
     *
     * @param  string  $finishedFilePath  The complete file. It must already be on the video drive
     * @param  string  $filename  The name the file had before: the uploaded file's name, or its name in the torrent.
     *                            The video keeps its extension
     */
    public function handle(Episode $episode, string $finishedFilePath, string $filename): Episode
    {
        $durationSeconds = $this->readVideoFileDurationAction->handle($finishedFilePath);

        return DB::transaction(function () use ($episode, $finishedFilePath, $filename, $durationSeconds): Episode {
            $newVideoPath = $this->resolveEpisodeVideoPathAction->handle($episode, filename: $filename);
            $this->deleteOldVideoAfterCommit($episode, $newVideoPath);

            $episode->update([
                'filename' => $filename,
                'has_video_file' => true,
                'video_file_size' => File::size($finishedFilePath),
                'video_duration_seconds' => $durationSeconds,
            ]);

            // The rename is last. If it fails, the row goes back to the old values. The rename replaces the old video
            // in one step, so the episode always has the old video or the new video
            File::ensureDirectoryExists(dirname($newVideoPath));
            File::move($finishedFilePath, $newVideoPath);

            return $episode->fresh();
        });
    }

    /**
     * The rename does not replace an old video at a different path, for example one with a different extension. This
     * deletes that video after the transaction is saved. If the transaction rolls back, the old video must stay.
     */
    private function deleteOldVideoAfterCommit(Episode $episode, string $newVideoPath): void
    {
        $oldVideoPath = $this->resolveEpisodeVideoPathAction->handle($episode);
        $hasOldVideoElsewhere = $episode->filename !== '' && $oldVideoPath !== $newVideoPath;

        if ($hasOldVideoElsewhere) {
            DB::afterCommit(fn () => $this->deleteVideoFileAction->handle($oldVideoPath));
        }
    }
}
