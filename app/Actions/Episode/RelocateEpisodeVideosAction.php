<?php

namespace App\Actions\Episode;

use App\Actions\Episode\VideoFile\MoveVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Models\Episode\Episode;
use Lorisleiva\Actions\Concerns\AsAction;

class RelocateEpisodeVideosAction
{
    use AsAction;

    public function __construct(
        private readonly MoveVideoFileAction $moveVideoFileAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    public function handle(string $oldBasePath, string $newBasePath): void
    {
        if ($oldBasePath === $newBasePath) {
            return;
        }

        Episode::query()
            ->with('entry.show.titles')
            ->where('filename', '!=', '')
            ->chunkById(200, function ($episodes) use ($oldBasePath, $newBasePath): void {
                foreach ($episodes as $episode) {
                    $oldPath = $this->resolveEpisodeVideoPathAction->handle($episode, basePath: $oldBasePath);
                    $newPath = $this->resolveEpisodeVideoPathAction->handle($episode, basePath: $newBasePath);

                    if ($oldPath !== $newPath) {
                        // Prune emptied folders up to the old base path, which they live under.
                        $this->moveVideoFileAction->handle($oldPath, $newPath, $oldBasePath);
                    }
                }
            });
    }
}
