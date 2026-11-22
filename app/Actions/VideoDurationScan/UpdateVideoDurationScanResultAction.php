<?php

namespace App\Actions\VideoDurationScan;

use App\Enums\VideoDurationScanErrorCode;
use App\Models\VideoDurationScan\VideoDurationScan;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVideoDurationScanResultAction
{
    use AsAction;

    /**
     * Ends the scan. Only the latest result is ever shown, and an upload burst can start many scans, so older finished
     * scans are deleted.
     */
    public function handle(VideoDurationScan $scan, ?VideoDurationScanErrorCode $errorCode = null): VideoDurationScan
    {
        return DB::transaction(function () use ($scan, $errorCode): VideoDurationScan {
            $scan->update(['running' => null, 'finished_at' => now(), 'error_code' => $errorCode]);
            VideoDurationScan::query()->whereNotNull('finished_at')->whereKeyNot($scan->id)->delete();

            return $scan->fresh();
        });
    }
}
