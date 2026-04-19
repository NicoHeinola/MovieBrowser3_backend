<?php

namespace App\Actions\Episode;

use App\Dtos\Episode\CreateEpisodeData;
use App\Models\Episode\Episode;
use App\Models\ShowEntry\ShowEntry;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateEpisodeAction
{
    use AsAction;

    public function handle(ShowEntry $showEntry, CreateEpisodeData $data): Episode
    {
        return $showEntry->episodes()->create([
            'name' => $data->name,
            'filename' => $data->filename,
            'sequence_number' => $data->sequenceNumber,
        ]);
    }
}
