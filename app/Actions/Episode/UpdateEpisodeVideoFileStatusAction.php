<?php

namespace App\Actions\Episode;

use App\Models\Episode\Episode;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateEpisodeVideoFileStatusAction
{
    use AsAction;

    public function handle(Episode $episode, bool $hasVideoFile): Episode
    {
        return DB::transaction(function () use ($episode, $hasVideoFile): Episode {
            $episode->update(['has_video_file' => $hasVideoFile]);

            return $episode->fresh();
        });
    }
}
