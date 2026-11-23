<?php

namespace App\Actions\Download;

use App\Dtos\QBittorrent\QBittorrentConnectionData;
use App\Enums\SettingKey;
use App\Exceptions\DownloadClientNotConfiguredException;
use App\Models\Setting\Setting;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveQBittorrentConnectionAction
{
    use AsAction;

    /**
     * The saved qBittorrent connection.
     *
     * @throws DownloadClientNotConfiguredException While downloads are off: no address or no download folder saved
     */
    public function handle(): QBittorrentConnectionData
    {
        $values = Setting::query()
            ->whereIn('key', [SettingKey::QBittorrentUrl->value, SettingKey::QBittorrentUsername->value, SettingKey::QBittorrentPassword->value, SettingKey::DownloadPath->value])
            ->get()
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => (string) $setting->value]);

        if (blank($values->get(SettingKey::QBittorrentUrl->value)) || blank($values->get(SettingKey::DownloadPath->value))) {
            throw new DownloadClientNotConfiguredException;
        }

        return new QBittorrentConnectionData(
            $values->get(SettingKey::QBittorrentUrl->value),
            $values->get(SettingKey::QBittorrentUsername->value, ''),
            $values->get(SettingKey::QBittorrentPassword->value, ''),
        );
    }
}
