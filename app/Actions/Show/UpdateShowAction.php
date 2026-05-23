<?php

namespace App\Actions\Show;

use App\Dtos\Show\UpdateShowData;
use App\Models\Show\Show;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowAction
{
    use AsAction;

    public function handle(UpdateShowData $data): Show
    {
        $data->show->fill(Arr::only($data->all(), $data->show->getFillable()))->save();

        return $data->show->fresh()->load('titles');
    }
}
