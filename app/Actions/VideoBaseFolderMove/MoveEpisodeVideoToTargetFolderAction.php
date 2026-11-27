<?php

namespace App\Actions\VideoBaseFolderMove;

use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Actions\File\CopyFileAction;
use App\Actions\File\DeleteEmptyDirectoriesAction;
use App\Exceptions\VideoFileMoveException;
use App\Models\Episode\Episode;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

class MoveEpisodeVideoToTargetFolderAction
{
    use AsAction;

    public function __construct(
        private readonly CopyFileAction $copyFileAction,
        private readonly DeleteEmptyDirectoriesAction $deleteEmptyDirectoriesAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    /**
     * Moves an episode's video from the source folder to the same place in the target folder. When a failed move goes
     * back, the target is the old video base folder. Then it deletes the source folders that are empty. It runs outside a transaction, for one file at a time. You can run it
     * again on the same episode: it does not touch a file that is already moved or that was never there.
     *
     * It copies the file first (as `.part`, then renames it), and then deletes the original. Thus a crash never leaves
     * only half a file. On one disk, the copy is a link, which uses no time or space.
     *
     * @param  string  $sourceFolder  The video base folder that has the video now
     * @param  string  $targetFolder  The video base folder to move the video to
     */
    public function handle(Episode $episode, string $sourceFolder, string $targetFolder): void
    {
        $fromPath = $this->resolveEpisodeVideoPathAction->handle($episode, videoBaseFolder: $sourceFolder);
        $toPath = $this->resolveEpisodeVideoPathAction->handle($episode, videoBaseFolder: $targetFolder);

        // When the move goes back: a worker that stopped during a copy into this folder left half a file. Delete it
        if (File::delete("{$fromPath}.part")) {
            $this->deleteEmptyDirectoriesAction->handle(dirname($fromPath), $sourceFolder);
        }

        // No file at the source: the file is missing, or an earlier run moved it
        if (!File::isFile($fromPath)) {
            return;
        }

        // The admin sees which file failed, for example a file that a player has open
        try {
            $this->moveVideo($fromPath, $toPath);
        } catch (Throwable $exception) {
            throw new VideoFileMoveException($fromPath, $exception);
        }

        $this->deleteEmptyDirectoriesAction->handle(dirname($fromPath), $sourceFolder);
    }

    private function moveVideo(string $fromPath, string $toPath): void
    {
        if (File::exists($toPath)) {
            // A complete copy from an earlier run that stopped before it deleted the original. A different file is not
            // ours
            if (!File::isFile($toPath) || File::size($toPath) !== File::size($fromPath)) {
                throw new RuntimeException("Refusing to overwrite an existing file: {$toPath}");
            }

            $this->deleteOriginal($fromPath);

            return;
        }

        $this->copyFileAction->handle($fromPath, $toPath);
        $this->deleteOriginal($fromPath);
    }

    /**
     * Throws when the original stays, for example because a program locks it. Without the error, nobody would see the
     * second copy. A new run completes the move, because the copy at the target has the same size.
     */
    private function deleteOriginal(string $fromPath): void
    {
        if (!File::delete($fromPath)) {
            throw new RuntimeException("Could not delete the original after copying it: {$fromPath}");
        }
    }
}
