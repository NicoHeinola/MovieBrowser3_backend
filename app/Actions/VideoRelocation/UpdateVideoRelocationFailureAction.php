<?php

namespace App\Actions\VideoRelocation;

use App\Enums\VideoRelocationErrorCode;
use App\Jobs\RelocateVideoFilesJob;
use App\Models\VideoRelocation\VideoRelocation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVideoRelocationFailureAction
{
    use AsAction;

    /**
     * A move that failed turns around and moves every file back, so the old folder holds everything again. A failed
     * move back ends the relocation: files may then sit in either folder, and the setting still names the old one.
     * `$failedPath` is the file that could not be moved, when known.
     */
    public function handle(VideoRelocation $relocation, ?string $failedPath = null): VideoRelocation
    {
        return DB::transaction(function () use ($relocation, $failedPath): VideoRelocation {
            $relocation->refresh();

            // Already ended: a worker killed after the last job finished brings that job back only to fail it, and
            // turning around then would move every file away from the folder the setting now names
            if (!$relocation->running) {
                return $relocation;
            }

            if ($relocation->reverting) {
                $relocation->update([
                    'running' => null,
                    'finished_at' => now(),
                    'error_code' => VideoRelocationErrorCode::RevertFailed,
                    'error_path' => $failedPath ?? $relocation->error_path,
                ]);

                return $relocation->fresh();
            }

            $relocation->update(['reverting' => true, 'moved' => 0, 'error_path' => $failedPath]);

            // Every episode again, from the start: files that never moved are simply skipped
            RelocateVideoFilesJob::dispatch($relocation)->afterCommit();

            return $relocation->fresh();
        });
    }
}
