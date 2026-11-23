<?php

namespace App\Http\Controllers;

use App\Actions\Download\GetDownloadDetailsAction;
use App\Actions\Download\UpdateDownloadFilesAction;
use App\Dtos\Download\UpdateDownloadFilesData;
use App\Http\Requests\Download\UpdateDownloadFilesRequest;
use App\Http\Resources\Download\DownloadResource;
use App\Models\Download\Download;
use Illuminate\Http\JsonResponse;

/**
 * Which files of a download become which episodes: one list for the whole download, replaced as a whole.
 */
class DownloadFilesController extends Controller
{
    public function update(Download $download, UpdateDownloadFilesRequest $request, UpdateDownloadFilesAction $updateAction, GetDownloadDetailsAction $getAction): JsonResponse
    {
        $updated = $updateAction->handle($download, UpdateDownloadFilesData::from($request->validated()));

        return DownloadResource::make($getAction->handle($updated))->response();
    }
}
