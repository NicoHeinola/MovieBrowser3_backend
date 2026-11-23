<?php

namespace App\Actions\Download;

use App\Enums\SettingKey;
use App\Models\Setting\Setting;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveDownloadPathAction
{
    use AsAction;

    /**
     * The download folder as the app sees it (`/mnt/d/Downloads`), or, with `$forClient`, as qBittorrent sees it. A
     * Windows qBittorrent sees a WSL drive mount as the drive (`D:\Downloads`); any other path is the same for both.
     */
    public function handle(bool $forClient = false): string
    {
        $path = rtrim((string) Setting::query()->whereKey(SettingKey::DownloadPath->value)->value('value'), '/');

        if (!$forClient || !preg_match('#^/mnt/([a-z])(/.*)?$#i', $path, $matches)) {
            return $path;
        }

        return strtoupper($matches[1]).':'.str_replace('/', '\\', $matches[2] ?? '\\');
    }
}
