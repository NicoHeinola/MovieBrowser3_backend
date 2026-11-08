<?php

namespace App\Actions\ShowEntryWatchStatus;

use App\Dtos\ShowEntryWatchStatus\WatchStatusChangeData;
use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
use App\Models\User\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class ListChangedShowEntryWatchStatusesAction
{
    use AsAction;

    /**
     * The user's entry statuses that differ from $known: new or changed ones, plus known ones whose row is gone (with
     * a null status). Deleted statuses leave no row to find by timestamp, so the whole set is compared every time.
     *
     * @param  Collection<int, WatchStatusChangeData>  $known  keyed by entry id; cleared entries stay with a null status
     * @return Collection<int, WatchStatusChangeData> keyed by entry id
     */
    public function handle(User $user, Collection $known): Collection
    {
        $current = ShowEntryWatchStatus::query()
            ->join('show_entries', 'show_entries.id', '=', 'show_entry_watch_statuses.show_entry_id')
            ->where('show_entry_watch_statuses.user_id', $user->id)
            ->get(['show_entry_watch_statuses.show_entry_id', 'show_entries.show_id', 'show_entry_watch_statuses.status'])
            ->map(fn (ShowEntryWatchStatus $status): WatchStatusChangeData => new WatchStatusChangeData(
                $status->show_id,
                $status->show_entry_id,
                $status->status,
            ))
            ->keyBy('showEntryId');

        $changed = $current->reject(fn (WatchStatusChangeData $status): bool => $known->get($status->showEntryId)?->status === $status->status);

        // Only entries that still had a status count as cleared; ones already sent as null stay quiet.
        $cleared = $known
            ->reject(fn (WatchStatusChangeData $status): bool => $status->status === null || $current->has($status->showEntryId))
            ->map(fn (WatchStatusChangeData $status): WatchStatusChangeData => new WatchStatusChangeData($status->showId, $status->showEntryId, null));

        return $changed->union($cleared);
    }
}
