<?php

namespace App\Actions\Episode\VideoFile;

use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class MoveVideoFileAction
{
    use AsAction;

    public function __construct(private readonly PruneEmptyDirectoriesAction $pruneEmptyDirectoriesAction) {}

    public function handle(string $fromPath, string $toPath, string $pruneStopAt): void
    {
        if ($fromPath === $toPath) {
            return;
        }

        if (!File::exists($fromPath)) {
            return;
        }

        File::ensureDirectoryExists(dirname($toPath));

        File::move($fromPath, $toPath);

        $this->pruneEmptyDirectoriesAction->handle(
            dirname($fromPath),
            $pruneStopAt,
        );
    }
}
