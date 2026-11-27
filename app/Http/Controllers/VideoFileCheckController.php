<?php

namespace App\Http\Controllers;

use App\Actions\VideoFileCheck\StartVideoFileCheckAction;
use App\Dtos\VideoFile\VideoFileCheckData;
use App\Http\Resources\VideoFile\VideoFileCheckResource;
use App\Models\Episode\Episode;
use App\Models\VideoFileCheck\VideoFileCheck;
use Illuminate\Http\JsonResponse;

class VideoFileCheckController extends Controller
{
    public function show(): JsonResponse
    {
        return VideoFileCheckResource::make($this->getStatus())->response();
    }

    public function store(StartVideoFileCheckAction $action): JsonResponse
    {
        $action->handle();

        return VideoFileCheckResource::make($this->getStatus())->response()->setStatusCode(202);
    }

    private function getStatus(): VideoFileCheckData
    {
        $runningCheck = VideoFileCheck::query()->active()->first();
        $lastFinishedCheck = VideoFileCheck::query()->whereNotNull('finished_at')->latest('id')->first();

        return new VideoFileCheckData(
            running: $runningCheck !== null,
            checked: $runningCheck->checked ?? 0,
            total: $runningCheck->total ?? 0,
            finishedAt: $lastFinishedCheck?->finished_at,
            errorCode: $lastFinishedCheck?->error_code,
            missingVideoFilesCount: Episode::query()->lostVideoFile()->count(),
            unknownVideoDurationsCount: Episode::query()->unknownVideoDuration()->count(),
        );
    }
}
