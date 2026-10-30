<?php

namespace App\Actions\ShowEntryStatus;

use App\Dtos\ShowEntryStatus\UpdateShowEntryStatusData;
use App\Models\ShowEntryStatus\ShowEntryStatus;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowEntryStatusAction
{
    use AsAction;

    public function handle(UpdateShowEntryStatusData $data): ShowEntryStatus
    {
        return DB::transaction(function () use ($data): ShowEntryStatus {
            return ShowEntryStatus::query()->updateOrCreate(
                ['show_entry_id' => $data->showEntry->id, 'user_id' => $data->user->id],
                ['status' => $data->status],
            );
        });
    }
}
