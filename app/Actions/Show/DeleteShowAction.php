<?php

namespace App\Actions\Show;

use App\Actions\Episode\VideoFile\DeleteVideoFileAction;
use App\Models\Show\Show;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowAction
{
    use AsAction;

    public function __construct(private readonly DeleteVideoFileAction $deleteVideoFileAction) {}

    public function handle(Show $show): void
    {
        $show->loadMissing('entries.episodes');

        foreach ($show->entries as $entry) {
            foreach ($entry->episodes as $episode) {
                if ($episode->filename === '') {
                    continue;
                }

                $this->deleteVideoFileAction->handle(
                    $episode->videoPath(),
                    $episode->videoBasePath(),
                );
            }
        }

        $show->delete();
    }
}
