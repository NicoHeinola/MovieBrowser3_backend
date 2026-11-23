<?php

namespace App\Http\Controllers;

use App\Actions\Download\CreateDownloadAction;
use App\Actions\Download\GetDownloadDetailsAction;
use App\Dtos\Download\CreateDownloadData;
use App\Http\Requests\Download\StoreDownloadRequest;
use App\Http\Resources\Download\DownloadResource;
use App\Models\Download\Download;
use Illuminate\Http\JsonResponse;

class DownloadController extends Controller
{
    public function store(StoreDownloadRequest $request, CreateDownloadAction $createAction, GetDownloadDetailsAction $getAction): JsonResponse
    {
        $download = $createAction->handle(CreateDownloadData::from($request->validated()));

        return DownloadResource::make($getAction->handle($download))->response()->setStatusCode(201);
    }

    public function show(Download $download, GetDownloadDetailsAction $action): JsonResponse
    {
        return DownloadResource::make($action->handle($download))->response();
    }
}
