<?php

namespace App\Actions\Episode\VideoFile\Path;

use App\Enums\SettingKey;
use App\Models\Setting\Setting;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveVideoBaseFolderAction
{
    use AsAction;

    /**
     * The folder all videos are in, from the settings.
     */
    public function handle(): string
    {
        $videoBaseFolder = Setting::query()->whereKey(SettingKey::VideoBaseFolder->value)->value('value');

        return rtrim((string) ($videoBaseFolder ?: storage_path('app/videos')), '/\\');
    }
}
