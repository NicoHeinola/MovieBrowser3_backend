<?php

namespace App\Actions\VideoBaseFolderMove;

use App\Enums\VideoBaseFolderMoveErrorCode;
use App\Jobs\MoveVideoBaseFolderJob;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RevertVideoBaseFolderMoveAction
{
    use AsAction;

    /**
     * A move that failed turns around and moves every file back, so the old folder holds everything again. A failed
     * move back ends the folder move: files may then sit in either folder, and the setting still names the old one.
     * `$failedPath` is the file that could not be moved, when known.
     */
    public function handle(VideoBaseFolderMove $folderMove, ?string $failedPath = null): VideoBaseFolderMove
    {
        return DB::transaction(function () use ($folderMove, $failedPath): VideoBaseFolderMove {
            $folderMove->refresh();

            // Already ended: a worker killed after the last job finished brings that job back only to fail it, and
            // turning around then would move every file away from the folder the setting now names
            if (!$folderMove->running) {
                return $folderMove;
            }

            if ($folderMove->reverting) {
                $folderMove->update([
                    'running' => null,
                    'finished_at' => now(),
                    'error_code' => VideoBaseFolderMoveErrorCode::RevertFailed,
                    'error_path' => $failedPath ?? $folderMove->error_path,
                ]);

                return $folderMove->fresh();
            }

            $folderMove->update(['reverting' => true, 'moved' => 0, 'error_path' => $failedPath]);

            // Every episode again, from the start: files that never moved are simply skipped
            MoveVideoBaseFolderJob::dispatch($folderMove)->afterCommit();

            return $folderMove->fresh();
        });
    }
}
