<?php

namespace App\Actions\VideoFileCheck;

use App\Enums\VideoFileCheckErrorCode;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FinishVideoFileCheckAction
{
    use AsAction;

    /**
     * Ends the check. Only the latest result is ever shown, so older ones are deleted.
     */
    public function handle(VideoFileCheck $check, ?VideoFileCheckErrorCode $errorCode = null): void
    {
        DB::transaction(function () use ($check, $errorCode): void {
            $check->update(['running' => null, 'finished_at' => now(), 'error_code' => $errorCode]);
            VideoFileCheck::query()->whereNotNull('finished_at')->whereKeyNot($check->id)->delete();
        });
    }
}
