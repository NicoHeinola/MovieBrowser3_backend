<?php

namespace App\Actions\File;

use Lorisleiva\Actions\Concerns\AsAction;

class DeleteEmptyDirectoriesAction
{
    use AsAction;

    /**
     * Deletes a folder and the folders above it, as long as they are empty.
     *
     * @param  string  $stopAt  The first folder above it that stays
     */
    public function handle(string $directory, string $stopAt): void
    {
        $directory = rtrim($directory, '/\\');
        $stopAt = rtrim($stopAt, '/\\');

        // rmdir itself refuses a folder that isn't empty, so nothing in one can be lost
        while ($directory !== $stopAt && @rmdir($directory)) {
            $directory = dirname($directory);
        }
    }
}
