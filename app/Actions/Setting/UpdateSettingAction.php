<?php

namespace App\Actions\Setting;

use App\Actions\Episode\RelocateEpisodeVideosAction;
use App\Actions\Episode\VideoFile\ResolveVideoBasePathAction;
use App\Dtos\Setting\UpdateSettingData;
use App\Models\Setting\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateSettingAction
{
    use AsAction;

    public function __construct(
        private readonly RelocateEpisodeVideosAction $relocateEpisodeVideosAction,
        private readonly ResolveVideoBasePathAction $resolveVideoBasePathAction,
    ) {}

    public function handle(UpdateSettingData $data): Setting
    {
        return DB::transaction(function () use ($data): Setting {
            $isVideoBasePath = $data->setting->key === 'video_base_path';
            $oldBasePath = $isVideoBasePath ? $this->resolveVideoBasePathAction->handle() : null;

            $data->setting->update([
                'value' => $data->value,
            ]);

            // Resolved settings such as the video base path are memoized with once().
            Once::flush();

            if ($isVideoBasePath) {
                $this->relocateEpisodeVideosAction->handle($oldBasePath, $this->resolveVideoBasePathAction->handle());
            }

            return $data->setting->fresh();
        });
    }
}
