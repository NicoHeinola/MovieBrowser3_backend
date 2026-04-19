<?php

namespace App\Http\Controllers;

use App\Actions\Setting\UpdateSettingAction;
use App\Dtos\Setting\UpdateSettingData;
use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Http\Resources\Setting\SettingResource;
use App\Models\Setting\Setting;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::query()
            ->orderBy('key')
            ->jsonPaginate();

        return SettingResource::collection($settings)->response();
    }

    public function update(Setting $setting, UpdateSettingRequest $request, UpdateSettingAction $updateSettingAction): JsonResponse
    {
        $updatedSetting = $updateSettingAction->handle(UpdateSettingData::from([
            'setting' => $setting,
            'value' => $request->validated()['value'],
        ]));

        return SettingResource::make($updatedSetting)->response();
    }
}
