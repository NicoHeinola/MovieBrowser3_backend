<?php

namespace App\Actions\EpisodePlayback;

use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\ShowWatchProgress\UpdateShowWatchProgressAction;
use App\Dtos\ShowWatchProgress\WatchPositionData;
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
     * When the user's progress is already on this episode, VLC resumes at the saved position, unless $fromStart is set.
     *
     * VLC opens before the transaction so its slow startup holds no lock, and a failed launch saves no progress.
     */
    public function handle(Episode $episode, User $user, bool $fromStart = false): ShowWatchProgress
    {
        $resume = $fromStart ? null : $this->resumableProgress($episode, $user);
        $startSeconds = $resume?->position_seconds ?? 0;

        [$paths, $startIndex] = $this->buildPlaylist($episode);
        $vlc = $this->openVlcPlaylistAction->handle($paths, $startIndex, $startSeconds);

        return DB::transaction(function () use ($episode, $user, $vlc, $resume): ShowWatchProgress {
            // A resumed episode keeps its position; anything else starts unknown until the tracking job reports it.
            $progress = $this->updateShowWatchProgressAction->handle(
                $episode,
                $user,
                $resume ? new WatchPositionData($resume->position_seconds, $resume->duration_seconds) : null,
            );

            TrackVlcPlaybackJob::queueNextCheck($user, $vlc)->afterCommit();

            return $progress;
        });
    }

    /**
     * The user's progress when it points into this episode, or null to start from the beginning.
     */
    private function resumableProgress(Episode $episode, User $user): ?ShowWatchProgress
    {
        return ShowWatchProgress::query()
            ->where('user_id', $user->id)
            ->where('episode_id', $episode->id)
            ->where('position_seconds', '>', 0)
            ->first();
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
