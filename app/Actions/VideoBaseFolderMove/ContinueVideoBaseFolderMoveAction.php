<?php

namespace App\Actions\VideoBaseFolderMove;

use App\Models\Episode\Episode;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use Lorisleiva\Actions\Concerns\AsAction;

class ContinueVideoBaseFolderMoveAction
{
    use AsAction;

    public function __construct(
        private readonly FinishVideoBaseFolderMoveAction $finishVideoBaseFolderMoveAction,
        private readonly MoveEpisodeVideoToTargetFolderAction $moveEpisodeVideoToTargetFolderAction,
    ) {}

    /**
     * Moves the video of the next episode. When no episode is left, it finishes the move.
     *
     * @param  int  $afterId  The id of the episode moved last
     * @return int|null The id of the moved episode. Null when the move is finished.
     */
    public function handle(VideoBaseFolderMove $folderMove, int $afterId = 0): ?int
    {
        $sourceFolder = $folderMove->getSourceFolder();
        $targetFolder = $folderMove->getTargetFolder();

        // Two names for the same folder: each video would be its own target. It would look like a copy from an earlier
        // run, and the move would delete it
        if ($this->isSameFolder($sourceFolder, $targetFolder)) {
            $this->finishVideoBaseFolderMoveAction->handle($folderMove);

            return null;
        }

        $nextEpisode = Episode::query()
            ->with('entry.show.titles')
            ->withVideoFilename()
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->first();

        if ($nextEpisode === null) {
            $this->finishVideoBaseFolderMoveAction->handle($folderMove);

            return null;
        }

        $this->moveEpisodeVideoToTargetFolderAction->handle($nextEpisode, $sourceFolder, $targetFolder);
        $folderMove->increment('moved');

        return $nextEpisode->id;
    }

    /**
     * Also true for a different spelling of the same folder: `//`, a `/.` at the end, or a symlink.
     */
    private function isSameFolder(string $sourceFolder, string $targetFolder): bool
    {
        if ($sourceFolder === $targetFolder) {
            return true;
        }

        $realSourceFolder = realpath($sourceFolder);
        $realTargetFolder = realpath($targetFolder);

        // A folder that does not exist yet is a different folder
        return $realTargetFolder !== false && $realTargetFolder === $realSourceFolder;
    }
}
