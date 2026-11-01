<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetDashboardStatsAction;
use App\Http\Resources\Dashboard\DashboardStatsResource;
use Illuminate\Http\JsonResponse;

class DashboardStatsController extends Controller
{
    public function show(GetDashboardStatsAction $action): JsonResponse
    {
        return DashboardStatsResource::make($action->handle())->response();
    }
}
