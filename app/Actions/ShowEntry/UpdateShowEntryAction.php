<?php

namespace App\Actions\ShowEntry;

use App\Dtos\ShowEntry\UpdateShowEntryData;
use App\Models\ShowEntry\ShowEntry;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class UpdateShowEntryAction
{
    use AsAction;

    public function handle(UpdateShowEntryData $data): ShowEntry
    {
        $attributes = [];

        if (!($data->type instanceof Optional)) {
            $attributes['type'] = $data->type;
        }

        if (!($data->name instanceof Optional)) {
            $attributes['name'] = $data->name;
        }

        if (!($data->sortOrder instanceof Optional)) {
            $attributes['sort_order'] = $data->sortOrder;
        }

        if ($attributes !== []) {
            $data->showEntry->update($attributes);
        }

        return $data->showEntry->fresh();
    }
}
