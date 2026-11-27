<?php

namespace App\Actions\Download\Client;

use App\Enums\SettingKey;
use App\Models\Setting\Setting;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveDownloadFolderAction
{
    use AsAction;

    /**
     * The folder all downloads go to, such as `/mnt/d/Downloads`.
     *
     * @param  bool  $isForClient  As qBittorrent sees it: a Windows one sees a WSL drive mount as the drive
     *                             (`D:\Downloads`). Any other path is the same for both.
     */
    public function handle(bool $isForClient = false): string
    {
        $path = rtrim((string) Setting::query()->whereKey(SettingKey::DownloadPath->value)->value('value'), '/');

        if (!$isForClient || !preg_match('#^/mnt/([a-z])(/.*)?$#i', $path, $matches)) {
            return $path;
        }

        return strtoupper($matches[1]).':'.str_replace('/', '\\', $matches[2] ?? '\\');
    }
}
