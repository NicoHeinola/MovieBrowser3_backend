<?php

namespace App\Actions\Episode;

use App\Dtos\Episode\UpdateEpisodeData;
use App\Models\Episode\Episode;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateEpisodeAction
{
    use AsAction;

    public function handle(UpdateEpisodeData $data): Episode
    {
        $data->episode->fill(Arr::only($data->all(), $data->episode->getFillable()))->save();

        return $data->episode->fresh();
    }
}
