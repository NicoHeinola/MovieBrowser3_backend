<?php

namespace App\Actions\Episode;

use App\Actions\Episode\VideoFile\StoreVideoFileAction;
use App\Dtos\Episode\CreateEpisodeData;
use App\Models\Episode\Episode;
use App\Models\ShowEntry\ShowEntry;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class CreateEpisodeAction
{
    use AsAction;

    public function __construct(private readonly StoreVideoFileAction $storeVideoFileAction) {}

    public function handle(ShowEntry $showEntry, CreateEpisodeData $data): Episode
    {
        $episode = $showEntry->episodes()->create([
            'name' => $data->name,
            'filename' => '',
            'sequence_number' => $data->sequenceNumber,
        ]);

        if (!($data->file instanceof Optional)) {
            $episode = $this->storeVideoFileAction->handle($episode, $data->file);
        }

        return $episode->fresh();
    }
}
