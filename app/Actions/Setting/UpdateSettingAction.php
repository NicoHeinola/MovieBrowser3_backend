<?php

namespace App\Actions\Setting;

use App\Dtos\Setting\UpdateSettingData;
use App\Models\Setting\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateSettingAction
{
    use AsAction;

    /**
     * Saves the value only. The video base path moves files first, so the video relocation calls this once they moved.
     */
    public function handle(UpdateSettingData $data): Setting
    {
        return DB::transaction(function () use ($data): Setting {
            $data->setting->update([
                'value' => $data->value,
            ]);

            // Resolved settings such as the video base path are memoized with once().
            Once::flush();

            return $data->setting->fresh();
        });
    }
}
