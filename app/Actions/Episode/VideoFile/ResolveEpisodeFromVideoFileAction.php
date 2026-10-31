<?php

namespace App\Actions\Episode\VideoFile;

use App\Models\Episode\Episode;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveEpisodeFromVideoFileAction
{
    use AsAction;

    /**
     * Finds the episode a video file belongs to. The reverse of ResolveEpisodeVideoPathAction: every video file is
     * named `{episodeId}_{episodeName}.{ext}`, so the id is the number before the first underscore.
     */
    public function handle(string $filename): ?Episode
    {
        if (!preg_match('/^(\d+)_/', basename($filename), $matches)) {
            return null;
        }

        return Episode::query()->find((int) $matches[1]);
    }
}
