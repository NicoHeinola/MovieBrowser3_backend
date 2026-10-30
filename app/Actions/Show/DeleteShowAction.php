<?php

namespace App\Actions\Show;

use App\Actions\Episode\DeleteEpisodeVideosAction;
use App\Models\Show\Show;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowAction
{
    use AsAction;

    public function __construct(private readonly DeleteEpisodeVideosAction $deleteEpisodeVideosAction) {}

    public function handle(Show $show): void
    {
        $show->loadMissing('titles', 'entries.episodes');

        DB::transaction(function () use ($show): void {
            $this->deleteEpisodeVideosAction->handle($show->entries->flatMap->episodes);

            $show->delete();
        });
    }
}
