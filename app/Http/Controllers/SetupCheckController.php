<?php

namespace App\Http\Controllers;

use App\Actions\Setup\CheckSetupSettingsAction;
use App\Dtos\Setup\CheckSetupSettingsData;
use App\Http\Requests\Setup\StoreSetupCheckRequest;
use App\Http\Resources\Setup\SetupChecksResource;
use Illuminate\Http\JsonResponse;

class SetupCheckController extends Controller
{
    /**
     * Checks the sent setup values without saving them, so 200 rather than 201.
     */
    public function store(StoreSetupCheckRequest $request, CheckSetupSettingsAction $action): JsonResponse
    {
        $checks = $action->handle(CheckSetupSettingsData::from($request->validated()));

        return SetupChecksResource::make($checks)->response();
    }
}
