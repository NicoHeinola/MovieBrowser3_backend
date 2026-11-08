<?php

namespace App\Actions\Episode\VideoFile;

use App\Models\Episode\Episode;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveEpisodeVideoFileSizeAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    /**
     * Bytes of the episode's video file on disk, or null when it's missing.
     * An episode without a filename resolves to its folder, which isFile rejects.
     */
    public function handle(Episode $episode): ?int
    {
        $path = $this->resolveEpisodeVideoPathAction->handle($episode);

        return File::isFile($path) ? File::size($path) : null;
    }
}
