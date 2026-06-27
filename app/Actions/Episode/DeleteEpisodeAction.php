<?php

namespace App\Actions\Episode;

use App\Actions\Episode\VideoFile\DeleteVideoFileAction;
use App\Models\Episode\Episode;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteEpisodeAction
{
    use AsAction;

    public function __construct(private readonly DeleteVideoFileAction $deleteVideoFileAction) {}

    public function handle(Episode $episode): void
    {
        if ($episode->filename !== '') {
            $this->deleteVideoFileAction->handle(
                $episode->videoPath(),
                $episode->videoBasePath(),
            );
        }

        $episode->delete();
    }
}
