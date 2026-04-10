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
    public function update(string $key, UpdateSettingRequest $request, UpdateSettingAction $updateSettingAction): JsonResponse
    {
        $setting = Setting::where('key', $key)->firstOrFail();

        $updatedSetting = $updateSettingAction->handle(new UpdateSettingData(
            setting: $setting,
            value: $request->validated()['value'],
        ));

        return SettingResource::make($updatedSetting)->response();
    }
}
