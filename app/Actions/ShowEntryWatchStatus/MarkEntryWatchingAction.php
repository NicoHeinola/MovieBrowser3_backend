<?php

namespace App\Actions\ShowEntryWatchStatus;

use App\Dtos\ShowEntryWatchStatus\UpdateShowEntryWatchStatusData;
use App\Enums\WatchStatus;
use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
use App\Models\User\User;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkEntryWatchingAction
{
    use AsAction;

    public function __construct(private readonly UpdateShowEntryWatchStatusAction $updateShowEntryWatchStatusAction) {}

    /**
     * Sets the user's status of the entry to watching. A completed entry stays completed, so watching it again does
     * not undo that.
     */
    public function handle(ShowEntry $entry, User $user): void
    {
        $isCompleted = ShowEntryWatchStatus::query()
            ->where('show_entry_id', $entry->id)
            ->where('user_id', $user->id)
            ->where('status', WatchStatus::Completed)
            ->exists();

        if ($isCompleted) {
            return;
        }

        $this->updateShowEntryWatchStatusAction->handle(
            new UpdateShowEntryWatchStatusData($entry, $user, WatchStatus::Watching),
        );
    }
}
