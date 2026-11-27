<?php

namespace App\Actions\Setting;

use App\Dtos\Setting\UpdateSettingData;
use App\Models\Setting\Setting;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateSettingAction
{
    use AsAction;

    /**
     * Saves the value only. A change of the video base folder must move the files first, so the video base folder move
     * calls this when all files are in the new folder.
     */
    public function handle(UpdateSettingData $data): Setting
    {
        $data->setting->update(['value' => $data->value]);

        return $data->setting->fresh();
    }
}
