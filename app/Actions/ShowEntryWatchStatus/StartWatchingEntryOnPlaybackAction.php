<?php

namespace App\Actions\ShowEntryWatchStatus;

use App\Dtos\ShowEntryWatchStatus\UpdateShowEntryWatchStatusData;
use App\Enums\WatchStatus;
use App\Models\Episode\Episode;
use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class StartWatchingEntryOnPlaybackAction
{
    use AsAction;

    public function __construct(private readonly UpdateShowEntryWatchStatusAction $updateShowEntryWatchStatusAction) {}

    /**
     * Marks the episode's entry as watching for the user when playback of it starts. A completed entry stays completed,
     * so rewatching it doesn't undo that, and one already watching writes nothing.
     */
    public function handle(Episode $episode, User $user): void
    {
        DB::transaction(function () use ($episode, $user): void {
            $status = ShowEntryWatchStatus::query()
                ->where('show_entry_id', $episode->show_entry_id)
                ->where('user_id', $user->id)
                ->value('status');

            if (in_array($status, [WatchStatus::Watching, WatchStatus::Completed], true)) {
                return;
            }

            $this->updateShowEntryWatchStatusAction->handle(
                new UpdateShowEntryWatchStatusData($episode->entry, $user, WatchStatus::Watching),
            );
        });
    }
}
