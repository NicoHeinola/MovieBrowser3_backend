<?php

namespace App\Actions\Setting;

use App\Dtos\Setting\UpdateSettingData;
use App\Models\Setting\Setting;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateSettingAction
{
    use AsAction;

    public function handle(UpdateSettingData $data): Setting
    {
        $data->setting->update([
            'value' => $data->value,
        ]);

        return $data->setting->fresh();
    }
}
