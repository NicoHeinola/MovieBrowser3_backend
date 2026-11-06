<?php

namespace App\Actions\Episode\VideoFile;

use App\Dtos\VideoFile\VideoFileCheckData;
use App\Models\Episode\Episode;
use App\Models\VideoFileCheck\VideoFileCheck;
use Lorisleiva\Actions\Concerns\AsAction;

class GetVideoFileCheckAction
{
    use AsAction;

    /**
     * The running check's progress, how the last finished one ended, and how many uploaded files are gone now.
     */
    public function handle(): VideoFileCheckData
    {
        $active = VideoFileCheck::query()->active()->first();
        $lastFinished = VideoFileCheck::query()->whereNotNull('finished_at')->latest('id')->first();

        return new VideoFileCheckData(
            running: $active !== null,
            checked: $active->checked ?? 0,
            total: $active->total ?? 0,
            finishedAt: $lastFinished?->finished_at,
            errorCode: $lastFinished?->error_code,
            missingVideoFilesCount: Episode::query()->lostVideoFile()->count(),
        );
    }
}
