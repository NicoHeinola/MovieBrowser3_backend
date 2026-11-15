<?php

namespace App\Actions\Episode\VideoFile;

use App\Exceptions\VideoFileMoveException;
use App\Models\Episode\Episode;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

class RelocateEpisodeVideoAction
{
    use AsAction;

    public function __construct(
        private readonly CopyVideoFileAction $copyVideoFileAction,
        private readonly PruneEmptyDirectoriesAction $pruneEmptyDirectoriesAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    /**
     * Moves the episode's video from under `$fromBasePath` to the same place under `$toBasePath` and prunes the emptied
     * source folders. Runs outside any transaction, one file at a time, so it is safe to run again on the same episode:
     * a file already moved, or never there, is left alone.
     *
     * Between disks the file is copied (as `.part`, then renamed) before the original is deleted, so a crash never
     * leaves only half a file. On one disk a rename is enough.
     */
    public function handle(Episode $episode, string $fromBasePath, string $toBasePath): void
    {
        $fromPath = $this->resolveEpisodeVideoPathAction->handle($episode, basePath: $fromBasePath);
        $toPath = $this->resolveEpisodeVideoPathAction->handle($episode, basePath: $toBasePath);

        // A copy towards this folder that a killed worker left half-written; moving back would otherwise skip it
        if (File::delete("{$fromPath}.part")) {
            $this->pruneEmptyDirectoriesAction->handle(dirname($fromPath), $fromBasePath);
        }

        // Nothing at the source: the file is missing, or an earlier run already moved it
        if (!File::isFile($fromPath)) {
            return;
        }

        // The admin sees which file failed, e.g. one a player still has open
        try {
            $this->move($fromPath, $toPath);
        } catch (Throwable $exception) {
            throw new VideoFileMoveException($fromPath, $exception);
        }

        $this->pruneEmptyDirectoriesAction->handle(dirname($fromPath), $fromBasePath);
    }

    private function move(string $fromPath, string $toPath): void
    {
        if (File::exists($toPath)) {
            // A copy that finished but died before deleting its original; anything else is someone else's file
            if (!File::isFile($toPath) || File::size($toPath) !== File::size($fromPath)) {
                throw new RuntimeException("Refusing to overwrite an existing file: {$toPath}");
            }

            $this->deleteOriginal($fromPath);

            return;
        }

        if ($this->onSameDisk($fromPath, $toPath)) {
            File::ensureDirectoryExists(dirname($toPath));

            if (!File::move($fromPath, $toPath)) {
                throw new RuntimeException("Could not move {$fromPath} to {$toPath}");
            }

            return;
        }

        $this->copy($fromPath, $toPath);
        $this->deleteOriginal($fromPath);
    }

    /**
     * A locked original would otherwise stay behind unnoticed as a full second copy. Running again finishes the move:
     * the copy at the target has the same size.
     */
    private function deleteOriginal(string $fromPath): void
    {
        if (!File::delete($fromPath)) {
            throw new RuntimeException("Could not delete the original after copying it: {$fromPath}");
        }
    }

    private function copy(string $fromPath, string $toPath): void
    {
        $partPath = "{$toPath}.part";

        // Left behind by a copy whose worker died; CopyVideoFileAction refuses to start next to one
        File::delete($partPath);

        try {
            $this->copyVideoFileAction->handle($fromPath, $toPath);
        } finally {
            // No transaction here, so CopyVideoFileAction's rollback cleanup never runs: a failed copy cleans up here
            File::delete($partPath);
        }
    }

    /**
     * The target folder may not exist yet, so its nearest existing parent stands in for it.
     */
    protected function onSameDisk(string $fromPath, string $toPath): bool
    {
        $target = dirname($toPath);

        while (!is_dir($target) && dirname($target) !== $target) {
            $target = dirname($target);
        }

        $fromStat = @stat($fromPath);
        $toStat = @stat($target);

        return $fromStat !== false && $toStat !== false && $fromStat['dev'] === $toStat['dev'];
    }
}
