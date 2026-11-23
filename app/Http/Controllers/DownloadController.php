<?php

namespace App\Http\Controllers;

use App\Actions\Download\CreateDownloadAction;
use App\Actions\Download\DeleteDownloadAction;
use App\Actions\Download\GetDownloadDetailsAction;
use App\Actions\Download\ListDownloadsAction;
use App\Dtos\Download\CreateDownloadData;
use App\Http\Requests\Download\DestroyDownloadRequest;
use App\Http\Requests\Download\StoreDownloadRequest;
use App\Http\Resources\Download\DownloadResource;
use App\Models\Download\Download;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DownloadController extends Controller
{
    public function index(ListDownloadsAction $action): JsonResponse
    {
        return DownloadResource::collection($action->handle())->response();
    }

    public function store(StoreDownloadRequest $request, CreateDownloadAction $createAction, GetDownloadDetailsAction $getAction): JsonResponse
    {
        $download = $createAction->handle(CreateDownloadData::from($request->validated()));

        return DownloadResource::make($getAction->handle($download))->response()->setStatusCode(201);
    }

    public function show(Download $download, GetDownloadDetailsAction $action): JsonResponse
    {
        return DownloadResource::make($action->handle($download))->response();
    }

    public function destroy(Download $download, DestroyDownloadRequest $request, DeleteDownloadAction $action): Response
    {
        $action->handle($download, $request->boolean('remove_episodes'));

        return response()->noContent();
    }
}
