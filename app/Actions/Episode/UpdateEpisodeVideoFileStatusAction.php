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
            // A lost file has no size; a found one keeps its size until the next check measures it
            $episode->update($hasVideoFile ? ['has_video_file' => true] : ['has_video_file' => false, 'video_file_size' => null]);

            return $episode->fresh();
        });
    }
}
