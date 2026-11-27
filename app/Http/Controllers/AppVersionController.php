<?php

namespace App\Http\Controllers;

use App\Dtos\AppVersion\AppVersionData;
use App\Http\Resources\AppVersion\AppVersionResource;
use App\Services\GitHub\GitHubService;
use Illuminate\Http\JsonResponse;

class AppVersionController extends Controller
{
    public function show(GitHubService $gitHubService): JsonResponse
    {
        $appVersion = new AppVersionData(
            backendVersion: trim((string) file_get_contents(base_path('VERSION'))),
            latestBackendRelease: $gitHubService->getLatestRelease(config('releases.backend_repo_url')),
            latestFrontendRelease: $gitHubService->getLatestRelease(config('releases.frontend_repo_url')),
        );

        return AppVersionResource::make($appVersion)->response();
    }
}
