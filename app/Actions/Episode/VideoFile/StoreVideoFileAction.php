<?php

namespace App\Actions\Episode\VideoFile;

use App\Actions\Episode\QueueEpisodeVideoDurationsAction;
use App\Models\Episode\Episode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreVideoFileAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly ResolveVideoBasePathAction $resolveVideoBasePathAction,
        private readonly PruneEmptyDirectoriesAction $pruneEmptyDirectoriesAction,
        private readonly QueueEpisodeVideoDurationsAction $queueEpisodeVideoDurationsAction,
    ) {}

    /**
     * A video already at the target path is kept as a backup until commit, so a rollback restores it.
     * The new file's length is read by a length scan once this commits, because reading a Windows drive can be slow.
     */
    public function handle(Episode $episode, UploadedFile $file, ?string $filename = null): Episode
    {
        return DB::transaction(function () use ($episode, $file, $filename): Episode {
            $resolvedFilename = basename($filename ?? $file->getClientOriginalName());
            $targetPath = $this->resolveEpisodeVideoPathAction->handle($episode, filename: $resolvedFilename);
            $basePath = $this->resolveVideoBasePathAction->handle();
            $backupPath = File::exists($targetPath) ? "{$targetPath}.replaced-".Str::random(8) : null;

            DB::afterRollBack(function () use ($targetPath, $backupPath, $basePath): void {
                File::delete($targetPath);

                if ($backupPath !== null) {
                    File::move($backupPath, $targetPath);
                } else {
                    $this->pruneEmptyDirectoriesAction->handle(dirname($targetPath), $basePath);
                }
            });

            if ($backupPath !== null) {
                File::move($targetPath, $backupPath);

                DB::afterCommit(fn () => File::delete($backupPath));
            }

            File::ensureDirectoryExists(dirname($targetPath));
            $file->move(dirname($targetPath), basename($targetPath));

            $episode->update([
                'filename' => $resolvedFilename,
                'has_video_file' => true,
                'video_file_size' => File::size($targetPath),
                // A replaced file's old length no longer applies; null also drops it from a scan reading the old file
                'video_duration_seconds' => null,
                'video_duration_queued_at' => null,
            ]);

            DB::afterCommit(fn () => $this->queueEpisodeVideoDurationsAction->handle(episodeIds: [$episode->id]));

            return $episode->fresh();
        });
    }
}
