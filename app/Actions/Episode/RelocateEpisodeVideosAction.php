<?php

namespace App\Actions\Episode;

use App\Actions\Episode\VideoFile\MoveVideoFileAction;
use App\Models\Episode\Episode;
use Lorisleiva\Actions\Concerns\AsAction;

class RelocateEpisodeVideosAction
{
    use AsAction;

    public function __construct(private readonly MoveVideoFileAction $moveVideoFileAction) {}

    public function handle(string $oldBasePath, string $newBasePath): void
    {
        if ($oldBasePath === $newBasePath) {
            return;
        }

        Episode::query()
            ->with('entry.show')
            ->where('filename', '!=', '')
            ->chunkById(200, function ($episodes) use ($oldBasePath, $newBasePath): void {
                foreach ($episodes as $episode) {
                    $oldPath = $episode->videoPath($oldBasePath);
                    $newPath = $episode->videoPath($newBasePath);

                    if ($oldPath !== $newPath) {
                        $this->moveVideoFileAction->handle($oldPath, $newPath, $newBasePath);
                    }
                }
            });
    }
}
