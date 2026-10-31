<?php

namespace App\Actions\EpisodePlayback;

use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\ShowWatchProgress\UpdateShowWatchProgressAction;
use App\Jobs\TrackVlcPlaybackJob;
use App\Models\Episode\Episode;
use App\Models\ShowWatchProgress\ShowWatchProgress;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateEpisodePlaybackAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly OpenVlcPlaylistAction $openVlcPlaylistAction,
        private readonly UpdateShowWatchProgressAction $updateShowWatchProgressAction,
    ) {}

    /**
     * Plays the episode in VLC with the rest of its entry in the playlist, remembers it as the user's progress and
     * starts following the VLC window so later episodes played there are remembered too.
     *
     * VLC opens before the transaction so its slow startup holds no lock, and a failed launch saves no progress.
     */
    public function handle(Episode $episode, User $user): ShowWatchProgress
    {
        [$paths, $startIndex] = $this->buildPlaylist($episode);
        $vlc = $this->openVlcPlaylistAction->handle($paths, $startIndex);

        return DB::transaction(function () use ($episode, $user, $vlc): ShowWatchProgress {
            $progress = $this->updateShowWatchProgressAction->handle($episode, $user);

            TrackVlcPlaybackJob::queueNextCheck($user, $vlc)->afterCommit();

            return $progress;
        });
    }

    /**
     * Every episode of the entry that has a video file, in order, plus where the clicked episode sits in that list.
     *
     * @return array{list<string>, int}
     */
    private function buildPlaylist(Episode $episode): array
    {
        $paths = $episode->entry->episodes
            ->mapWithKeys(fn (Episode $entryEpisode): array => [$entryEpisode->id => $this->resolveEpisodeVideoPathAction->handle($entryEpisode)])
            ->filter(fn (string $path): bool => File::isFile($path));

        return [$paths->values()->all(), $paths->keys()->search($episode->id)];
    }
}
