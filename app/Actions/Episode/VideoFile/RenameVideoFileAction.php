<?php

namespace App\Actions\Episode\VideoFile;

use App\Actions\Episode\VideoFile\Path\ResolveVideoBaseFolderAction;
use App\Actions\File\DeleteEmptyDirectoriesAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class RenameVideoFileAction
{
    use AsAction;

    public function __construct(
        private readonly DeleteEmptyDirectoriesAction $deleteEmptyDirectoriesAction,
        private readonly ResolveVideoBaseFolderAction $resolveVideoBaseFolderAction,
    ) {}

    /**
     * Gives a video a different path in the same video base folder, with PHP's `rename()`. Then it deletes the folders
     * that are empty. If the transaction rolls back, the video goes back to its old path.
     *
     * @param  string  $fromPath  The video's path now
     * @param  string  $toPath  The video's new path
     */
    public function handle(string $fromPath, string $toPath): void
    {
        if ($fromPath === $toPath || !File::exists($fromPath)) {
            return;
        }

        $videoBaseFolder = $this->resolveVideoBaseFolderAction->handle();

        File::ensureDirectoryExists(dirname($toPath));

        File::move($fromPath, $toPath);

        DB::afterRollBack(function () use ($fromPath, $toPath, $videoBaseFolder): void {
            File::ensureDirectoryExists(dirname($fromPath));
            File::move($toPath, $fromPath);

            $this->deleteEmptyDirectoriesAction->handle(dirname($toPath), $videoBaseFolder);
        });

        $this->deleteEmptyDirectoriesAction->handle(dirname($fromPath), $videoBaseFolder);
    }
}
