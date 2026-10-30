<?php

namespace App\Actions\ShowEntryStatus;

use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowEntryStatus\ShowEntryStatus;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowEntryStatusAction
{
    use AsAction;

    public function handle(ShowEntry $showEntry, User $user): void
    {
        DB::transaction(function () use ($showEntry, $user): void {
            ShowEntryStatus::query()->where('show_entry_id', $showEntry->id)->where('user_id', $user->id)->delete();
        });
    }
}
