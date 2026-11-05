<?php

namespace App\Actions\Episode\VideoFile;

use App\Enums\VideoFileCheckErrorCode;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVideoFileCheckResultAction
{
    use AsAction;

    public function handle(VideoFileCheck $check, ?VideoFileCheckErrorCode $errorCode = null): VideoFileCheck
    {
        return DB::transaction(function () use ($check, $errorCode): VideoFileCheck {
            $check->update(['running' => null, 'finished_at' => now(), 'error_code' => $errorCode]);

            return $check->fresh();
        });
    }
}
