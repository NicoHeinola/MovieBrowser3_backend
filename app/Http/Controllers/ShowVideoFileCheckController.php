<?php

namespace App\Http\Controllers;

use App\Actions\VideoFileCheck\CheckShowVideoFilesAction;
use App\Http\Resources\VideoFile\ShowVideoFileCheckResource;
use App\Models\Show\Show;
use Illuminate\Http\JsonResponse;

class ShowVideoFileCheckController extends Controller
{
    public function store(Show $show, CheckShowVideoFilesAction $action): JsonResponse
    {
        return ShowVideoFileCheckResource::make($action->handle($show))->response();
    }
}
