<?php

namespace App\Actions\ShowLink;

use App\Dtos\ShowLink\UpdateShowLinkData;
use App\Models\ShowLink\ShowLink;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowLinkAction
{
    use AsAction;

    public function handle(UpdateShowLinkData $data): ShowLink
    {
        $data->showLink->fill(Arr::only($data->all(), $data->showLink->getFillable()))->save();

        return $data->showLink->fresh();
    }
}
