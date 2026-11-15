<?php

namespace App\Actions\VideoRelocation;

use App\Dtos\VideoRelocation\VideoRelocationData;
use App\Models\VideoRelocation\VideoRelocation;
use Lorisleiva\Actions\Concerns\AsAction;

class GetVideoRelocationAction
{
    use AsAction;

    /**
     * The running relocation, else the last one, else an empty status.
     */
    public function handle(): VideoRelocationData
    {
        $relocation = VideoRelocation::query()->latest('id')->first();

        return new VideoRelocationData(
            running: $relocation?->running ?? false,
            reverting: $relocation->reverting ?? false,
            moved: $relocation->moved ?? 0,
            total: $relocation->total ?? 0,
            fromPath: $relocation?->from_path,
            toPath: $relocation?->to_path,
            finishedAt: $relocation?->finished_at,
            errorCode: $relocation?->error_code,
            errorPath: $relocation?->error_path,
        );
    }
}
