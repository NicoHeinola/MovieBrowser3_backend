<?php

namespace App\Actions\Episode\VideoFile;

use App\Models\Episode\Episode;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVideoFileCheckProgressAction
{
    use AsAction;

    /**
     * Counts from the database rather than adding up chunks, so episodes deleted during the check can't skew it.
     */
    public function handle(VideoFileCheck $check, int $lastCheckedId): VideoFileCheck
    {
        return DB::transaction(function () use ($check, $lastCheckedId): VideoFileCheck {
            $check->update([
                'checked' => Episode::query()->where('id', '<=', $lastCheckedId)->count(),
                'total' => Episode::query()->count(),
            ]);

            return $check->fresh();
        });
    }
}
