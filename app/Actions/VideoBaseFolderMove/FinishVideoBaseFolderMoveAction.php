<?php

namespace App\Actions\VideoBaseFolderMove;

use App\Actions\Setting\UpdateSettingAction;
use App\Dtos\Setting\UpdateSettingData;
use App\Enums\SettingKey;
use App\Enums\VideoBaseFolderMoveErrorCode;
use App\Models\Setting\Setting;
use App\Models\VideoBaseFolderMove\VideoBaseFolderMove;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FinishVideoBaseFolderMoveAction
{
    use AsAction;

    public function __construct(private readonly UpdateSettingAction $updateSettingAction) {}

    /**
     * Ends the move of the video base folder. All videos are in the target folder now.
     */
    public function handle(VideoBaseFolderMove $folderMove): void
    {
        // After a failure, the videos moved back to the old folder. The setting stays, and the move ends as failed
        if ($folderMove->reverting) {
            $folderMove->update(['running' => null, 'finished_at' => now(), 'error_code' => VideoBaseFolderMoveErrorCode::Failed]);

            return;
        }

        DB::transaction(function () use ($folderMove): void {
            // The videos are in the new folder now. The app must look for them there
            $setting = Setting::query()->findOrFail(SettingKey::VideoBaseFolder->value);
            $this->updateSettingAction->handle(new UpdateSettingData($setting, $folderMove->to_path));

            $folderMove->update(['running' => null, 'finished_at' => now(), 'error_code' => null]);
        });
    }
}
