<?php

namespace App\Http\Controllers;

use App\Actions\VideoBaseFolderMove\StartVideoBaseFolderMoveAction;
use App\Dtos\VideoBaseFolderMove\VideoBaseFolderMoveData;
use App\Http\Requests\VideoBaseFolderMove\StoreVideoBaseFolderMoveRequest;
use App\Http\Resources\VideoBaseFolderMove\VideoBaseFolderMoveResource;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use Illuminate\Http\JsonResponse;

class VideoBaseFolderMoveController extends Controller
{
    public function show(): JsonResponse
    {
        return VideoBaseFolderMoveResource::make($this->getStatus())->response();
    }

    public function store(StoreVideoBaseFolderMoveRequest $request, StartVideoBaseFolderMoveAction $action): JsonResponse
    {
        $videoBaseFolder = $request->validated('video_base_folder');
        $action->handle($videoBaseFolder);

        return VideoBaseFolderMoveResource::make($this->getStatus())->response()->setStatusCode(202);
    }

    /**
     * The running folder move, or the last one when none runs.
     */
    private function getStatus(): VideoBaseFolderMoveData
    {
        $folderMove = VideoBaseFolderMove::query()->latest('id')->first();

        return new VideoBaseFolderMoveData(
            running: $folderMove->running ?? false,
            reverting: $folderMove->reverting ?? false,
            moved: $folderMove->moved ?? 0,
            total: $folderMove->total ?? 0,
            fromPath: $folderMove?->from_path,
            toPath: $folderMove?->to_path,
            finishedAt: $folderMove?->finished_at,
            errorCode: $folderMove?->error_code,
            errorPath: $folderMove?->error_path,
        );
    }
}
