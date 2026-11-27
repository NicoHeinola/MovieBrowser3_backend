<?php

namespace App\Jobs;

use App\Actions\EpisodePlayback\UpdateProgressFromVlcAction;
use App\Dtos\EpisodePlayback\VlcConnectionData;
use App\Models\User\User;
use App\Services\Vlc\VlcService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Follows a VLC window opened by playback: every few seconds (config vlc.track_interval_seconds) it asks VLC which
 * file is playing and how far into it VLC is, and saves both as the user's progress, so moving to the next episode
 * inside VLC is remembered too and the next playback can resume there. Stops once VLC is closed.
 */
class TrackVlcPlaybackJob implements ShouldQueue
{
    use Queueable;

    // A user deleted while VLC is open simply ends the tracking.
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public User $user,
        public VlcConnectionData $vlcConnection,
    ) {}

    /**
     * Queues the next check of this VLC window, `vlc.track_interval_seconds` (VLC_TRACK_INTERVAL_SECONDS) from now.
     */
    public static function queueNextCheck(User $user, VlcConnectionData $vlcConnection): PendingDispatch
    {
        return self::dispatch($user, $vlcConnection)->delay((int) config('vlc.track_interval_seconds'));
    }

    public function handle(UpdateProgressFromVlcAction $updateProgressFromVlcAction): void
    {
        $vlcService = new VlcService($this->vlcConnection);
        $status = $vlcService->getStatus();

        // VLC no longer answers: the window was closed, so there is nothing left to follow
        if ($status === null) {
            return;
        }

        // Before saving: one save that fails must not end the tracking for the rest of the film
        self::queueNextCheck($this->user, $this->vlcConnection);
        $updateProgressFromVlcAction->handle($this->user, $status);
    }
}
