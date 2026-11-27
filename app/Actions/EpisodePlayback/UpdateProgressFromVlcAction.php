<?php

namespace App\Actions\EpisodePlayback;

use App\Actions\ShowEntryWatchStatus\UpdateShowEntryWatchStatusAction;
use App\Actions\ShowWatchProgress\UpdateShowWatchProgressAction;
use App\Dtos\EpisodePlayback\VlcStatusData;
use App\Dtos\ShowEntryWatchStatus\UpdateShowEntryWatchStatusData;
use App\Enums\WatchStatus;
use App\Models\Episode\Episode;
use App\Models\User\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProgressFromVlcAction
{
    use AsAction;

    public function __construct(
        private readonly UpdateShowWatchProgressAction $updateShowWatchProgressAction,
        private readonly UpdateShowEntryWatchStatusAction $updateShowEntryWatchStatusAction,
    ) {}

    /**
     * Saves what VLC is playing as the user's progress, and the entry as completed once its last episode is nearly over. A
     * paused or stopped VLC keeps the last one.
     */
    public function handle(User $user, VlcStatusData $status): void
    {
        // VLC plays nothing, so there is no position to save
        if (!$status->isPlaying()) {
            return;
        }

        $episode = $this->findEpisode($status->filename);

        if ($episode === null) {
            return;
        }

        $position = $status->getPosition();
        $this->updateShowWatchProgressAction->handle($episode, $user, $position);

        // The credits of the last episode (or of a movie): the entry has been watched, whatever status the user set meanwhile
        if ($position->isNearEnd() && $this->isLastEpisode($episode)) {
            $this->updateShowEntryWatchStatusAction->handle(
                new UpdateShowEntryWatchStatusData($episode->entry, $user, WatchStatus::Completed),
            );
        }
    }

    private function isLastEpisode(Episode $episode): bool
    {
        // Sorted by sequence number, the order the entry plays in
        $lastEpisode = $episode->entry->episodes->last();

        return $episode->is($lastEpisode);
    }

    /**
     * Every video file is named `{episodeId}_{episodeName}.{ext}` (`ResolveEpisodeVideoPathAction`). Another file the user
     * opened in the same VLC isn't.
     */
    private function findEpisode(?string $filename): ?Episode
    {
        if (!preg_match('/^(\d+)_/', (string) $filename, $matches)) {
            return null;
        }

        return Episode::query()->find($matches[1]);
    }
}
