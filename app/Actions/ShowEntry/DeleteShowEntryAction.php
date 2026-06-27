<?php

namespace App\Actions\ShowEntry;

use App\Actions\Episode\VideoFile\DeleteVideoFileAction;
use App\Models\ShowEntry\ShowEntry;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowEntryAction
{
    use AsAction;

    public function __construct(private readonly DeleteVideoFileAction $deleteVideoFileAction) {}

    public function handle(ShowEntry $showEntry): void
    {
        $showEntry->loadMissing('episodes');

        foreach ($showEntry->episodes as $episode) {
            if ($episode->filename === '') {
                continue;
            }

            $this->deleteVideoFileAction->handle(
                $episode->videoPath(),
                $episode->videoBasePath(),
            );
        }

        $showEntry->delete();
    }
}
