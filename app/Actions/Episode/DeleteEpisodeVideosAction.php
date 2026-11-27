<?php

namespace App\Actions\Episode;

use App\Actions\Download\Client\StopDownloadsOfDeletedEpisodesAction;
use App\Actions\Episode\VideoFile\DeleteVideoFileAction;
use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Models\Episode\Episode;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteEpisodeVideosAction
{
    use AsAction;

    public function __construct(
        private readonly DeleteVideoFileAction $deleteVideoFileAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly StopDownloadsOfDeletedEpisodesAction $stopDownloadsOfDeletedEpisodesAction,
    ) {}

    /**
     * Gets the video paths now, while the episodes' relations still exist. It deletes the files after the transaction is
     * saved, so a rollback keeps them on disk. Then it also stops the downloads of torrent files for the episodes.
     *
     * @param  iterable<Episode>  $episodes
     */
    public function handle(iterable $episodes): void
    {
        $this->stopDownloadsOfDeletedEpisodesAction->handle($episodes);

        $videoPaths = collect($episodes)
            ->filter(fn (Episode $episode): bool => $episode->filename !== '')
            ->map(fn (Episode $episode): string => $this->resolveEpisodeVideoPathAction->handle($episode))
            ->all();

        DB::afterCommit(function () use ($videoPaths): void {
            foreach ($videoPaths as $videoPath) {
                $this->deleteVideoFileAction->handle($videoPath);
            }
        });
    }
}
