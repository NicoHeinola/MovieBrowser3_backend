<?php

namespace App\Actions\Setting;

use App\Actions\Episode\RelocateEpisodeVideosAction;
use App\Dtos\Setting\UpdateSettingData;
use App\Models\Setting\Setting;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateSettingAction
{
    use AsAction;

    public function __construct(private readonly RelocateEpisodeVideosAction $relocateEpisodeVideosAction) {}

    public function handle(UpdateSettingData $data): Setting
    {
        $oldValue = $data->setting->value;

        $data->setting->update([
            'value' => $data->value,
        ]);

        if ($data->setting->key === 'video_base_path') {
            $this->relocateEpisodeVideosAction->handle(
                rtrim((string) ($oldValue ?? ''), '/\\'),
                rtrim((string) ($data->setting->value ?? ''), '/\\'),
            );
        }

        return $data->setting->fresh();
    }
}
