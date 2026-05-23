<?php

namespace App\Actions\ShowEntry;

use App\Dtos\ShowEntry\UpdateShowEntryData;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowEntryAction
{
    use AsAction;

    public function handle(UpdateShowEntryData $data): ShowEntry
    {
        $data->showEntry->fill(Arr::only($data->all(), $data->showEntry->getFillable()))->save();

        return $data->showEntry->fresh();
    }
}
