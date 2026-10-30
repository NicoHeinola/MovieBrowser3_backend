<?php

namespace App\Actions\Episode;

use App\Models\Episode\Episode;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteEpisodeAction
{
    use AsAction;

    public function __construct(private readonly DeleteEpisodeVideosAction $deleteEpisodeVideosAction) {}

    public function handle(Episode $episode): void
    {
        DB::transaction(function () use ($episode): void {
            $this->deleteEpisodeVideosAction->handle([$episode]);

            $episode->delete();
        });
    }
}
