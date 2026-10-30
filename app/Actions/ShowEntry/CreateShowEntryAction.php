<?php

namespace App\Actions\ShowEntry;

use App\Dtos\ShowEntry\CreateShowEntryData;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowEntryAction
{
    use AsAction;

    public function handle(Show $show, CreateShowEntryData $data): ShowEntry
    {
        return DB::transaction(function () use ($show, $data): ShowEntry {
            return $show->entries()->create([
                'type' => $data->type,
                'name' => $data->name,
                'sort_order' => $data->sortOrder,
            ]);
        });
    }
}
