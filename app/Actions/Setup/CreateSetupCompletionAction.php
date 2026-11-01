<?php

namespace App\Actions\Setup;

use App\Dtos\Setup\SetupStatusData;
use App\Models\Setting\Setting;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateSetupCompletionAction
{
    use AsAction;

    public function __construct(
        private readonly GetSetupStatusAction $getSetupStatusAction,
    ) {}

    /**
     * Marks the first-login setup as done. Completing it again keeps the first time.
     */
    public function handle(): SetupStatusData
    {
        return DB::transaction(function (): SetupStatusData {
            $setting = Setting::query()->firstOrCreate(['key' => 'setup_completed_at'], ['type' => 'string', 'value' => '']);

            if (blank($setting->value)) {
                $setting->update(['value' => now()->toJSON()]);
            }

            return $this->getSetupStatusAction->handle();
        });
    }
}
