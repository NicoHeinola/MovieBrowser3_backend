<?php

namespace App\Actions\Episode\VideoFile;

use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class PruneEmptyDirectoriesAction
{
    use AsAction;

    public function handle(string $directory, string $stopAt): void
    {
        $currentDirectory = rtrim($directory, '/\\');
        $stopAt = rtrim($stopAt, '/\\');

        while ($currentDirectory !== '' && $currentDirectory !== $stopAt && File::isDirectory($currentDirectory) && count(File::files($currentDirectory)) === 0 && count(File::directories($currentDirectory)) === 0) {
            File::deleteDirectory($currentDirectory);

            $parentDirectory = dirname($currentDirectory);

            if ($parentDirectory === $currentDirectory) {
                break;
            }

            $currentDirectory = $parentDirectory;
        }
    }
}
