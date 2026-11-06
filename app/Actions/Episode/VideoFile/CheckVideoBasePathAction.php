<?php

namespace App\Actions\Episode\VideoFile;

use App\Models\Episode\Episode;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckVideoBasePathAction
{
    use AsAction;

    public function __construct(private readonly ResolveVideoBasePathAction $resolveVideoBasePathAction) {}

    /**
     * Whether the video base folder can be trusted to show which files exist.
     *
     * An empty folder counts as unreachable: a drive that isn't mounted still shows up as an empty folder in the
     * containers, so every episode looking missing may say nothing about the files. Uses scandir because the SPL directory
     * iterators skip entries on Windows drives.
     */
    public function handle(): bool
    {
        $basePath = $this->resolveVideoBasePathAction->handle();
        $entries = File::isDirectory($basePath) ? @scandir($basePath) : false;

        // scandir always lists `.` and `..`
        if ($entries !== false && count($entries) > 2) {
            return true;
        }

        // A library that expects no files is simply empty: a fresh install has no folder until the first upload
        return !Episode::query()->where('filename', '!=', '')->exists();
    }
}
