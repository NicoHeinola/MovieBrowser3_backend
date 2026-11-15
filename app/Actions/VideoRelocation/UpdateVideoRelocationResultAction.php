<?php

namespace App\Actions\VideoRelocation;

use App\Actions\Setting\UpdateSettingAction;
use App\Dtos\Setting\UpdateSettingData;
use App\Enums\SettingKey;
use App\Enums\VideoRelocationErrorCode;
use App\Models\Setting\Setting;
use App\Models\VideoRelocation\VideoRelocation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVideoRelocationResultAction
{
    use AsAction;

    public function __construct(private readonly UpdateSettingAction $updateSettingAction) {}

    /**
     * Ends a relocation whose files all moved: forwards, so the setting now points at `to_path`, or back after a
     * failure, so the setting stays and the relocation ends as failed.
     */
    public function handle(VideoRelocation $relocation): VideoRelocation
    {
        return DB::transaction(function () use ($relocation): VideoRelocation {
            if (!$relocation->reverting) {
                $this->updateSettingAction->handle(new UpdateSettingData(
                    Setting::query()->findOrFail(SettingKey::VideoBasePath->value),
                    $relocation->to_path,
                ));
            }

            $relocation->update([
                'running' => null,
                'finished_at' => now(),
                'error_code' => $relocation->reverting ? VideoRelocationErrorCode::Failed : null,
            ]);

            return $relocation->fresh();
        });
    }
}
