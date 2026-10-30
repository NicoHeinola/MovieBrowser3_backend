<?php

namespace App\Actions\ShowEntryWatchStatus;

use App\Dtos\ShowEntryWatchStatus\UpdateShowEntryWatchStatusData;
use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowEntryWatchStatusAction
{
    use AsAction;

    public function handle(UpdateShowEntryWatchStatusData $data): ShowEntryWatchStatus
    {
        return DB::transaction(function () use ($data): ShowEntryWatchStatus {
            return ShowEntryWatchStatus::query()->updateOrCreate(
                ['show_entry_id' => $data->showEntry->id, 'user_id' => $data->user->id],
                ['status' => $data->status],
            );
        });
    }
}
