<?php

namespace App\Actions\File;

use Lorisleiva\Actions\Concerns\AsAction;

class CreateFileLinkAction
{
    use AsAction;

    /**
     * Gives a file a second name (a hard link), and returns whether it could: not on another drive or mount, nor on a
     * drive without hard links.
     */
    public function handle(string $fromPath, string $toPath): bool
    {
        return @link($fromPath, $toPath);
    }
}
