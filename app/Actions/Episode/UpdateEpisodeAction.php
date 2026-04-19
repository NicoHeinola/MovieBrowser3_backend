<?php

namespace App\Actions\Episode;

use App\Dtos\Episode\UpdateEpisodeData;
use App\Models\Episode\Episode;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class UpdateEpisodeAction
{
    use AsAction;

    public function handle(UpdateEpisodeData $data): Episode
    {
        $attributes = [];

        if (!($data->name instanceof Optional)) {
            $attributes['name'] = $data->name;
        }

        if (!($data->filename instanceof Optional)) {
            $attributes['filename'] = $data->filename;
        }

        if (!($data->sequenceNumber instanceof Optional)) {
            $attributes['sequence_number'] = $data->sequenceNumber;
        }

        if ($attributes !== []) {
            $data->episode->update($attributes);
        }

        return $data->episode->fresh();
    }
}
