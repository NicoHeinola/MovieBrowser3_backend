<?php

namespace App\Http\Controllers;

use App\Actions\VideoRelocation\CreateVideoRelocationAction;
use App\Actions\VideoRelocation\GetVideoRelocationAction;
use App\Http\Requests\VideoRelocation\StoreVideoRelocationRequest;
use App\Http\Resources\VideoRelocation\VideoRelocationResource;
use Illuminate\Http\JsonResponse;

class VideoRelocationController extends Controller
{
    public function show(GetVideoRelocationAction $action): JsonResponse
    {
        return VideoRelocationResource::make($action->handle())->response();
    }

    public function store(StoreVideoRelocationRequest $request, CreateVideoRelocationAction $createAction, GetVideoRelocationAction $getAction): JsonResponse
    {
        $createAction->handle($request->string('video_base_path')->toString());

        return VideoRelocationResource::make($getAction->handle())->response()->setStatusCode(202);
    }
}
