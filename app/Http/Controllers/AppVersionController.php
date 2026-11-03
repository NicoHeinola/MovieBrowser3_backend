<?php

namespace App\Http\Controllers;

use App\Actions\AppVersion\GetAppVersionAction;
use App\Http\Resources\AppVersion\AppVersionResource;
use Illuminate\Http\JsonResponse;

class AppVersionController extends Controller
{
    public function show(GetAppVersionAction $action): JsonResponse
    {
        return AppVersionResource::make($action->handle())->response();
    }
}
