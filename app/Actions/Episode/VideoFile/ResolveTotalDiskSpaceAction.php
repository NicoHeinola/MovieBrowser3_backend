<?php

namespace App\Actions\Episode\VideoFile;

use Lorisleiva\Actions\Concerns\AsAction;

class ResolveTotalDiskSpaceAction
{
    use AsAction;

    /**
     * Total bytes of the disk that holds `$path`, or null when the system can't tell.
     */
    public function handle(string $path): ?int
    {
        $total = @disk_total_space($path);

        return $total === false ? null : (int) $total;
    }
}
