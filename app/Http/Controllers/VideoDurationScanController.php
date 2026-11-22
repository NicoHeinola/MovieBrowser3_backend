<?php

namespace App\Http\Controllers;

use App\Actions\Episode\QueueEpisodeVideoDurationsAction;
use App\Actions\VideoDurationScan\GetVideoDurationScanAction;
use App\Http\Requests\VideoDurationScan\StoreVideoDurationScanRequest;
use App\Http\Resources\VideoDurationScan\VideoDurationScanResource;
use Illuminate\Http\JsonResponse;

class VideoDurationScanController extends Controller
{
    public function show(GetVideoDurationScanAction $action): JsonResponse
    {
        return VideoDurationScanResource::make($action->handle())->response();
    }

    public function store(StoreVideoDurationScanRequest $request, QueueEpisodeVideoDurationsAction $queueAction, GetVideoDurationScanAction $getAction): JsonResponse
    {
        $queueAction->handle(
            showIds: $request->has('show_ids') ? array_map('intval', $request->validated('show_ids')) : null,
            onlyMissing: $request->boolean('only_missing'),
        );

        return VideoDurationScanResource::make($getAction->handle())->response()->setStatusCode(202);
    }
}
