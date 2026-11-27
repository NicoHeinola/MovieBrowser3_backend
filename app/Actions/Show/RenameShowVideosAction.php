<?php

namespace App\Actions\Show;

use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\Path\ResolveShowFolderNameAction;
use App\Actions\Episode\VideoFile\RenameVideoFileAction;
use App\Models\Show\Show;
use Lorisleiva\Actions\Concerns\AsAction;

class RenameShowVideosAction
{
    use AsAction;

    public function __construct(
        private readonly RenameVideoFileAction $renameVideoFileAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly ResolveShowFolderNameAction $resolveShowFolderNameAction,
    ) {}

    /**
     * Gives a show's videos the paths that its titles give now, after a change of its titles. It renames nothing when
     * the show's folder name is the same.
     *
     * @param  string  $oldFolderName  The show's folder name before the change
     */
    public function handle(Show $show, string $oldFolderName): void
    {
        $show->loadMissing('titles', 'entries.episodes');

        $newFolderName = $this->resolveShowFolderNameAction->handle($show);
        $episodesWithVideo = $show->entries->flatMap->episodes->where('filename', '!=', '');

        foreach ($episodesWithVideo as $episode) {
            $oldPath = $this->resolveEpisodeVideoPathAction->handle($episode, showName: $oldFolderName);
            $newPath = $this->resolveEpisodeVideoPathAction->handle($episode, showName: $newFolderName);

            $this->renameVideoFileAction->handle($oldPath, $newPath);
        }
    }
}
