<?php

namespace App\Actions\VideoDurationScan;

use App\Models\Episode\Episode;
use App\Models\VideoDurationScan\VideoDurationScan;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVideoDurationScanProgressAction
{
    use AsAction;

    /**
     * Adds a chunk's reads. The total is what was read plus what still waits, so episodes queued during the scan count.
     */
    public function handle(VideoDurationScan $scan, int $read): VideoDurationScan
    {
        return DB::transaction(function () use ($scan, $read): VideoDurationScan {
            $totalRead = $scan->read + $read;

            $scan->update([
                'read' => $totalRead,
                'total' => $totalRead + Episode::query()->whereNotNull('video_duration_queued_at')->count(),
            ]);

            return $scan->fresh();
        });
    }
}
