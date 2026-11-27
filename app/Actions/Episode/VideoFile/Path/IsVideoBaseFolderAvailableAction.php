<?php

namespace App\Actions\Episode\VideoFile\Path;

use App\Models\Episode\Episode;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use Lorisleiva\Actions\Concerns\AsAction;

class IsVideoBaseFolderAvailableAction
{
    use AsAction;

    public function __construct(private readonly ResolveVideoBaseFolderAction $resolveVideoBaseFolderAction) {}

    /**
     * Whether videos can be looked for and put in place right now: the folder isn't being moved, and its drive is
     * there.
     */
    public function handle(): bool
    {
        $isBeingMoved = VideoBaseFolderMove::query()->running()->exists();

        if ($isBeingMoved) {
            return false;
        }

        return $this->isDriveThere();
    }

    /**
     * A drive that isn't mounted shows up as an empty folder in the containers, and then every video looks missing.
     */
    private function isDriveThere(): bool
    {
        $videoBaseFolder = $this->resolveVideoBaseFolderAction->handle();

        if (!$this->isEmptyDirectory($videoBaseFolder)) {
            return true;
        }

        // A library that expects no videos has an empty folder, or none at all: the first import or upload makes it
        return !Episode::query()->withVideoFilename()->exists();
    }

    /**
     * Also true for a folder that doesn't exist or can't be read. Uses scandir because the SPL directory iterators skip
     * entries on Windows drives.
     */
    private function isEmptyDirectory(string $path): bool
    {
        $entries = @scandir($path);

        // scandir always lists `.` and `..`
        return $entries === false || count($entries) <= 2;
    }
}
