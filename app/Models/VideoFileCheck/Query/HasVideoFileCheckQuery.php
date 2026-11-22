<?php

namespace App\Models\VideoFileCheck\Query;

use App\Models\Query\HasStaleRunScopes;

trait HasVideoFileCheckQuery
{
    use HasStaleRunScopes;

    protected function staleAfterSeconds(): int
    {
        return (int) config('video_files.check_stale_after_seconds');
    }
}
