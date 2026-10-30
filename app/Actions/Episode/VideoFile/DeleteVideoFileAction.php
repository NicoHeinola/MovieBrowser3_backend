<?php

namespace App\Actions\Episode\VideoFile;

use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteVideoFileAction
{
    use AsAction;

    public function __construct(
        private readonly PruneEmptyDirectoriesAction $pruneEmptyDirectoriesAction,
        private readonly ResolveVideoBasePathAction $resolveVideoBasePathAction,
    ) {}

    public function handle(string $path, ?string $pruneStopAt = null): void
    {
        File::delete($path);

        $this->pruneEmptyDirectoriesAction->handle(
            dirname($path),
            $pruneStopAt ?? $this->resolveVideoBasePathAction->handle(),
        );
    }
}
