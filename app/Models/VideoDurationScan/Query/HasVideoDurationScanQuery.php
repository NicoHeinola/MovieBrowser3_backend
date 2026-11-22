<?php

namespace App\Models\VideoDurationScan\Query;

use App\Models\Query\HasStaleRunScopes;

trait HasVideoDurationScanQuery
{
    use HasStaleRunScopes;

    protected function staleAfterSeconds(): int
    {
        return (int) config('video_files.duration_scan_stale_after_seconds');
    }
}
