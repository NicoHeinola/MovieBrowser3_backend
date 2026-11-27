<?php

namespace App\Actions\Episode\VideoFile;

use App\Actions\Episode\VideoFile\Path\ResolveVideoBaseFolderAction;
use App\Actions\File\DeleteEmptyDirectoriesAction;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteVideoFileAction
{
    use AsAction;

    public function __construct(
        private readonly DeleteEmptyDirectoriesAction $deleteEmptyDirectoriesAction,
        private readonly ResolveVideoBaseFolderAction $resolveVideoBaseFolderAction,
    ) {}

    public function handle(string $path): void
    {
        $videoBaseFolder = $this->resolveVideoBaseFolderAction->handle();

        File::delete($path);
        $this->deleteEmptyDirectoriesAction->handle(dirname($path), $videoBaseFolder);
    }
}
