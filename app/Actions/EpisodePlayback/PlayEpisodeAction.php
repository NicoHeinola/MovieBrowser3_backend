<?php

namespace App\Actions\EpisodePlayback;

use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Actions\ShowEntryWatchStatus\MarkEntryWatchingAction;
use App\Actions\ShowWatchProgress\UpdateShowWatchProgressAction;
use App\Dtos\ShowWatchProgress\WatchPositionData;
use App\Enums\ValidationErrorCode;
use App\Jobs\TrackVlcPlaybackJob;
use App\Models\Episode\Episode;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowWatchProgress\ShowWatchProgress;
use App\Models\User\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class PlayEpisodeAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
        private readonly OpenVlcPlaylistAction $openVlcPlaylistAction,
        private readonly UpdateShowWatchProgressAction $updateShowWatchProgressAction,
        private readonly MarkEntryWatchingAction $markEntryWatchingAction,
    ) {}

    /**
     * Plays the episode in VLC with the rest of its entry in the playlist, remembers it as the user's progress, marks
     * the entry as watching and starts following the VLC window so later episodes played there are remembered too.
     * The playlist never leaves the entry, so marking it once here covers the whole playback.
     *
     * VLC opens before the transaction so its slow startup holds no lock, and a failed launch saves no progress.
     *
     * @param  bool  $shouldStartOver  Plays from the beginning; otherwise VLC resumes where the user left this episode
     */
    public function handle(Episode $episode, User $user, bool $shouldStartOver = false): ShowWatchProgress
    {
        $videoPaths = $this->getVideoPaths($episode->entry);

        if (!$videoPaths->has($episode->id)) {
            throw ValidationException::withMessages(['episode' => ValidationErrorCode::NoVideoFile->value]);
        }

        $startPosition = null;

        if (!$shouldStartOver) {
            $startPosition = $this->getSavedPosition($episode, $user);
        }

        $startIndex = $videoPaths->keys()->search($episode->id);
        $startSeconds = $startPosition->positionSeconds ?? 0;
        $vlcConnection = $this->openVlcPlaylistAction->handle($videoPaths->values()->all(), $startIndex, $startSeconds);

        return DB::transaction(function () use ($episode, $user, $vlcConnection, $startPosition): ShowWatchProgress {
            // A resumed episode keeps its position; anything else starts unknown until the tracking job reports it.
            $progress = $this->updateShowWatchProgressAction->handle($episode, $user, $startPosition);

            $this->markEntryWatchingAction->handle($episode->entry, $user);

            TrackVlcPlaybackJob::queueNextCheck($user, $vlcConnection)->afterCommit();

            return $progress;
        });
    }

    /**
     * Where the user left this episode, or null to start from the beginning.
     */
    private function getSavedPosition(Episode $episode, User $user): ?WatchPositionData
    {
        $progress = ShowWatchProgress::query()
            ->where('user_id', $user->id)
            ->where('episode_id', $episode->id)
            ->resumable()
            ->first();

        if ($progress === null) {
            return null;
        }

        return new WatchPositionData($progress->position_seconds, $progress->duration_seconds);
    }

    /**
     * The video of every episode of the entry that has one, in order.
     *
     * @return Collection<int, string> Keyed by episode id
     */
    private function getVideoPaths(ShowEntry $entry): Collection
    {
        return $entry->episodes
            ->mapWithKeys(fn (Episode $episode): array => [
                $episode->id => $this->resolveEpisodeVideoPathAction->handle($episode),
            ])
            ->filter(fn (string $path): bool => File::isFile($path));
    }
}
