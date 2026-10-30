<?php

namespace App\Actions\Show;

use App\Actions\Episode\VideoFile\MoveVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\ResolveShowVideoFolderNameAction;
use App\Models\Show\Show;
use Lorisleiva\Actions\Concerns\AsAction;

class RelocateShowVideosAction
{
    use AsAction;

    public function __construct(
        private readonly MoveVideoFileAction $moveVideoFileAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly ResolveShowVideoFolderNameAction $resolveShowVideoFolderNameAction,
    ) {}

    /**
     * Move a show's episode files after its folder name (primary title) changed.
     */
    public function handle(Show $show, string $oldFolderName): void
    {
        $show->loadMissing('titles', 'entries.episodes');

        $newFolderName = $this->resolveShowVideoFolderNameAction->handle($show);

        if ($oldFolderName === $newFolderName) {
            return;
        }

        foreach ($show->entries as $entry) {
            foreach ($entry->episodes as $episode) {
                if ($episode->filename === '') {
                    continue;
                }

                $this->moveVideoFileAction->handle(
                    $this->resolveEpisodeVideoPathAction->handle($episode, showName: $oldFolderName),
                    $this->resolveEpisodeVideoPathAction->handle($episode, showName: $newFolderName),
                );
            }
        }
    }
}
