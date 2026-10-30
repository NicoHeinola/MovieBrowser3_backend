<?php

namespace App\Actions\Episode\VideoFile;

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
    ) {}

    /**
     * A video already at the target path is kept as a backup until commit, so a rollback restores it.
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

            $episode->update(['filename' => $resolvedFilename]);

            return $episode->fresh();
        });
    }
}
