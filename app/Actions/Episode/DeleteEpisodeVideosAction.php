<?php

namespace App\Actions\Episode;

use App\Actions\Episode\VideoFile\DeleteVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Models\Episode\Episode;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteEpisodeVideosAction
{
    use AsAction;

    public function __construct(
        private readonly DeleteVideoFileAction $deleteVideoFileAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    /**
     * Resolve paths now, while the episode relations still exist, and delete the files only
     * once the surrounding transaction commits so a rollback keeps them on disk.
     *
     * @param  iterable<Episode>  $episodes
     */
    public function handle(iterable $episodes): void
    {
        $videoPaths = collect($episodes)
            ->filter(fn (Episode $episode): bool => $episode->filename !== '')
            ->map(fn (Episode $episode): string => $this->resolveEpisodeVideoPathAction->handle($episode))
            ->values()
            ->all();

        if ($videoPaths === []) {
            return;
        }

        DB::afterCommit(function () use ($videoPaths): void {
            foreach ($videoPaths as $path) {
                $this->deleteVideoFileAction->handle($path);
            }
        });
    }
}
