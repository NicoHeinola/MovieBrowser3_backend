<?php

namespace App\Http\Controllers;

use App\Actions\Setup\GetSetupStatusAction;
use App\Http\Resources\Setup\SetupStatusResource;
use Illuminate\Http\JsonResponse;

class SetupController extends Controller
{
    public function show(GetSetupStatusAction $action): JsonResponse
    {
        return SetupStatusResource::make($action->handle())->response();
    }
}
