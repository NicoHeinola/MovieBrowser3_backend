<?php

namespace App\Actions\Episode\VideoFile;

use App\Models\Setting\Setting;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveVideoBasePathAction
{
    use AsAction;

    public function handle(): string
    {
        return self::resolve();
    }

    /**
     * Memoized per request (static so every action instance shares it); call Once::flush() after changing the setting.
     */
    private static function resolve(): string
    {
        return once(function (): string {
            $basePath = Setting::query()->whereKey('video_base_path')->value('value');

            return rtrim((string) ($basePath ?: storage_path('app/videos')), '/\\');
        });
    }
}
