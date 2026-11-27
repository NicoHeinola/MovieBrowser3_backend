<?php

namespace App\Actions\File;

use App\Dtos\File\DiskSpaceData;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveDiskSpaceAction
{
    use AsAction;

    /**
     * The space of a disk, or null when the system can't tell.
     *
     * @param  string  $path  A folder on it. One that doesn't exist yet is fine: its nearest existing parent is measured.
     */
    public function handle(string $path): ?DiskSpaceData
    {
        while (!is_dir($path) && dirname($path) !== $path) {
            $path = dirname($path);
        }

        $freeBytes = @disk_free_space($path);
        $totalBytes = @disk_total_space($path);

        if ($freeBytes === false || $totalBytes === false) {
            return null;
        }

        return new DiskSpaceData((int) $freeBytes, (int) $totalBytes);
    }
}
