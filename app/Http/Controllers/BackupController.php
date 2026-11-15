<?php

namespace App\Http\Controllers;

use App\Actions\Backup\CreateBackupAction;
use App\Actions\Backup\ListBackupFilesAction;
use App\Http\Resources\Backup\BackupFileResource;
use Illuminate\Http\JsonResponse;

class BackupController extends Controller
{
    public function index(ListBackupFilesAction $action): JsonResponse
    {
        // In `data` like the other lists; resources are otherwise unwrapped
        return response()->json(['data' => BackupFileResource::collection($action->handle())]);
    }

    public function store(CreateBackupAction $action): JsonResponse
    {
        return BackupFileResource::make($action->handle())->response()->setStatusCode(201);
    }
}
