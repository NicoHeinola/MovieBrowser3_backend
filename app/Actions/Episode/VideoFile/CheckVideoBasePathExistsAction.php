<?php

namespace App\Actions\Episode\VideoFile;

use App\Models\Episode\Episode;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckVideoBasePathExistsAction
{
    use AsAction;

    public function __construct(private readonly ResolveVideoBasePathAction $resolveVideoBasePathAction) {}

    /**
     * Whether there is a base folder to check files in at all. Without one that exists and can be read, every file
     * looks missing, so no file can be judged.
     */
    public function handle(): bool
    {
        $basePath = $this->resolveVideoBasePathAction->handle();

        if (File::isDirectory($basePath) && File::isReadable($basePath)) {
            return true;
        }

        // A library that expects no files needs no folder: a fresh install has none until the first upload
        return !Episode::query()->where('filename', '!=', '')->exists();
    }
}
