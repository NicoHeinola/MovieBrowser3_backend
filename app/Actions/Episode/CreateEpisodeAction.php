<?php

namespace App\Actions\Episode;

use App\Actions\Episode\VideoFile\StoreUploadedVideoAction;
use App\Dtos\Episode\CreateEpisodeData;
use App\Models\Episode\Episode;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateEpisodeAction
{
    use AsAction;

    public function __construct(private readonly StoreUploadedVideoAction $storeUploadedVideoAction) {}

    public function handle(ShowEntry $showEntry, CreateEpisodeData $data): Episode
    {
        return DB::transaction(function () use ($showEntry, $data): Episode {
            $episode = $showEntry->episodes()->create([
                'name' => $data->name,
                'filename' => '',
                'has_video_file' => false,
                'sequence_number' => $data->sequenceNumber,
            ]);

            // The upload copies and reads the file inside this transaction. It locks only this episode, and a failed
            // upload also undoes the other changes
            if ($data->file instanceof UploadedFile) {
                $episode = $this->storeUploadedVideoAction->handle($episode, $data->file);
            }

            return $episode->fresh();
        });
    }
}
