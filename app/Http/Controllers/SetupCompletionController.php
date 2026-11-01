<?php

namespace App\Http\Controllers;

use App\Actions\Setup\CreateSetupCompletionAction;
use App\Http\Resources\Setup\SetupStatusResource;
use Illuminate\Http\JsonResponse;

class SetupCompletionController extends Controller
{
    public function store(CreateSetupCompletionAction $action): JsonResponse
    {
        return SetupStatusResource::make($action->handle())->response()->setStatusCode(201);
    }
}
