<?php

namespace App\Actions\Episode\VideoFile;

use Lorisleiva\Actions\Concerns\AsAction;

class ResolveFreeDiskSpaceAction
{
    use AsAction;

    /**
     * Free bytes on the disk that holds `$path`, or null when the system can't tell.
     * The path may not exist yet: the nearest existing parent folder is measured instead.
     */
    public function handle(string $path): ?int
    {
        while (!is_dir($path) && dirname($path) !== $path) {
            $path = dirname($path);
        }

        $free = @disk_free_space($path);

        return $free === false ? null : (int) $free;
    }
}
