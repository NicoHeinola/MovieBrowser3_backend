<?php

namespace App\Actions\ShowEntryWatchStatus;

use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
use App\Models\User\User;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowEntryWatchStatusAction
{
    use AsAction;

    public function handle(ShowEntry $showEntry, User $user): void
    {
        ShowEntryWatchStatus::query()->where('show_entry_id', $showEntry->id)->where('user_id', $user->id)->delete();
    }
}
