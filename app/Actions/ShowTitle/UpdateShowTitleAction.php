<?php

namespace App\Actions\ShowTitle;

use App\Dtos\ShowTitle\UpdateShowTitleData;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowTitleAction
{
    use AsAction;

    public function handle(UpdateShowTitleData $data): ShowTitle
    {
        return DB::transaction(function () use ($data): ShowTitle {
            if ($data->isPrimary === true) {
                $data->showTitle->show->titles()
                    ->where('is_primary', true)
                    ->where('id', '!=', $data->showTitle->id)
                    ->update(['is_primary' => false]);
            }

            $data->showTitle->fill(Arr::only($data->all(), $data->showTitle->getFillable()))->save();

            return $data->showTitle->fresh();
        });
    }
}
