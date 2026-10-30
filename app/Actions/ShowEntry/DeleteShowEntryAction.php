<?php

namespace App\Actions\ShowEntry;

use App\Actions\Episode\DeleteEpisodeVideosAction;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowEntryAction
{
    use AsAction;

    public function __construct(private readonly DeleteEpisodeVideosAction $deleteEpisodeVideosAction) {}

    public function handle(ShowEntry $showEntry): void
    {
        $showEntry->loadMissing('episodes');

        DB::transaction(function () use ($showEntry): void {
            $this->deleteEpisodeVideosAction->handle($showEntry->episodes);

            $showEntry->delete();
        });
    }
}
