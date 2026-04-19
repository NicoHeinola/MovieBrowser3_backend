<?php

namespace App\Actions\ShowLink;

use App\Dtos\ShowLink\UpdateShowLinkData;
use App\Models\ShowLink\ShowLink;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class UpdateShowLinkAction
{
    use AsAction;

    public function handle(UpdateShowLinkData $data): ShowLink
    {
        $attributes = [];

        if (!($data->targetShowId instanceof Optional)) {
            $attributes['target_show_id'] = $data->targetShowId;
        }

        if (!($data->type instanceof Optional)) {
            $attributes['type'] = $data->type;
        }

        if ($attributes !== []) {
            $data->showLink->update($attributes);
        }

        return $data->showLink->fresh();
    }
}
