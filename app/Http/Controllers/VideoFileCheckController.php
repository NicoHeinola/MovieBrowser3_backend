<?php

namespace App\Http\Controllers;

use App\Actions\Episode\VideoFile\CreateVideoFileCheckAction;
use App\Actions\Episode\VideoFile\GetVideoFileCheckAction;
use App\Http\Requests\VideoFile\StoreVideoFileCheckRequest;
use App\Http\Resources\VideoFile\VideoFileCheckResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class VideoFileCheckController extends Controller
{
    public function show(GetVideoFileCheckAction $action): JsonResponse
    {
        return VideoFileCheckResource::make($action->handle())->response();
    }

    public function store(StoreVideoFileCheckRequest $request, CreateVideoFileCheckAction $action): Response
    {
        $action->handle();

        return response()->noContent(202);
    }
}
