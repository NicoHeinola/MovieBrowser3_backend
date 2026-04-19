<?php

namespace App\Actions\Episode;

use App\Models\Episode\Episode;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteEpisodeAction
{
    use AsAction;

    public function handle(Episode $episode): void
    {
        $episode->delete();
    }
}
