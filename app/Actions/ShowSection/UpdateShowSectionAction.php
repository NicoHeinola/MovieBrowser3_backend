<?php

namespace App\Actions\ShowSection;

use App\Dtos\ShowSection\UpdateShowSectionData;
use App\Models\ShowSection\ShowSection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowSectionAction
{
    use AsAction;

    public function handle(UpdateShowSectionData $data): ShowSection
    {
        return DB::transaction(function () use ($data): ShowSection {
            $data->showSection->fill(Arr::only($data->all(), $data->showSection->getFillable()))->save();

            return $data->showSection->fresh();
        });
    }
}
