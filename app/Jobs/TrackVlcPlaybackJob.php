<?php

namespace App\Jobs;

use App\Actions\Episode\VideoFile\ResolveEpisodeFromVideoFileAction;
use App\Actions\EpisodePlayback\RequestVlcStatusAction;
use App\Actions\ShowWatchProgress\UpdateShowWatchProgressAction;
use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Models\User\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Follows a VLC window opened by playback: every few seconds (config vlc.track_interval_seconds) it asks VLC which
 * file is playing and saves that episode as the user's progress, so moving to the next episode inside VLC is
 * remembered too. Stops once VLC is closed.
 */
class TrackVlcPlaybackJob implements ShouldQueue
{
    use Queueable;

    // A user deleted while VLC is open simply ends the tracking.
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public User $user,
        public VlcConnectionData $vlc,
    ) {}

    /**
     * Queues the next check of this VLC window, `vlc.track_interval_seconds` (VLC_TRACK_INTERVAL_SECONDS) from now.
     */
    public static function queueNextCheck(User $user, VlcConnectionData $vlc): PendingDispatch
    {
        return self::dispatch($user, $vlc)->delay((int) config('vlc.track_interval_seconds'));
    }

    public function handle(
        RequestVlcStatusAction $requestVlcStatusAction,
        ResolveEpisodeFromVideoFileAction $resolveEpisodeFromVideoFileAction,
        UpdateShowWatchProgressAction $updateShowWatchProgressAction,
    ): void {
        $status = $requestVlcStatusAction->handle($this->vlc);

        // VLC no longer answers: the window was closed, so there is nothing left to follow.
        if ($status === null) {
            return;
        }

        // Queue the next check before saving anything, so one failed save does not end the tracking for good.
        self::queueNextCheck($this->user, $this->vlc);

        // Only an episode that is actually playing counts; a paused or stopped VLC keeps the last saved progress.
        $filename = data_get($status, 'information.category.meta.filename');
        $episode = data_get($status, 'state') === 'playing' && is_string($filename)
            ? $resolveEpisodeFromVideoFileAction->handle($filename)
            : null;

        if ($episode !== null) {
            $updateShowWatchProgressAction->handle($episode, $this->user);
        }
    }
}
