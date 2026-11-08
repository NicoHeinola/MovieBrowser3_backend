<?php

namespace App\Actions\ShowEntryWatchStatus;

use App\Dtos\ShowEntryWatchStatus\UpdateShowEntryWatchStatusData;
use App\Enums\WatchStatus;
use App\Models\Episode\Episode;
use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CompleteEntryOnLastEpisodeAction
{
    use AsAction;

    public function __construct(private readonly UpdateShowEntryWatchStatusAction $updateShowEntryWatchStatusAction) {}

    /**
     * Marks the episode's entry completed for the user when this is the entry's last episode. Does nothing for other
     * episodes or an entry that is already completed, so repeated checks while the credits play write nothing.
     */
    public function handle(Episode $episode, User $user): void
    {
        DB::transaction(function () use ($episode, $user): void {
            $entry = $episode->entry;

            // The last episode is the one with the highest sequence number, the same order the entry plays in.
            $lastEpisodeId = $entry->episodes()->reorder('sequence_number', 'desc')->value('id');

            if ($lastEpisodeId !== $episode->id) {
                return;
            }

            $alreadyCompleted = ShowEntryWatchStatus::query()
                ->where('show_entry_id', $entry->id)
                ->where('user_id', $user->id)
                ->where('status', WatchStatus::Completed)
                ->exists();

            if (!$alreadyCompleted) {
                $this->updateShowEntryWatchStatusAction->handle(
                    new UpdateShowEntryWatchStatusData($entry, $user, WatchStatus::Completed),
                );
            }
        });
    }
}
