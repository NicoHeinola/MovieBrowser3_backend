<?php

namespace App\Actions\Setup;

use App\Actions\Download\CheckQBittorrentConnectionAction;
use App\Actions\EpisodePlayback\CheckVlcBinaryAction;
use App\Dtos\QBittorrent\QBittorrentConnectionData;
use App\Dtos\Setup\CheckSetupSettingsData;
use App\Dtos\Setup\SetupCheckData;
use App\Enums\SettingKey;
use App\Models\Setting\Setting;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class CheckSetupSettingsAction
{
    use AsAction;

    public function __construct(
        private readonly CheckVlcBinaryAction $checkVlcBinaryAction,
        private readonly CheckMediaFolderPathAction $checkMediaFolderPathAction,
        private readonly CheckQBittorrentConnectionAction $checkQBittorrentConnectionAction,
    ) {}

    /**
     * Checks the setup values that were sent, without saving anything.
     *
     * @return array<string, SetupCheckData> Keyed by setting key.
     */
    public function handle(CheckSetupSettingsData $data): array
    {
        $checks = [];

        if (!$data->videoBasePath instanceof Optional) {
            $checks[SettingKey::VideoBasePath->value] = $this->checkMediaFolderPathAction->handle($data->videoBasePath);
        }

        if (!$data->vlcMediaPlayerPath instanceof Optional) {
            $checks[SettingKey::VlcMediaPlayerPath->value] = $this->checkVlcBinaryAction->handle($data->vlcMediaPlayerPath);
        }

        if (!$data->qbittorrentUrl instanceof Optional) {
            $checks[SettingKey::QBittorrentUrl->value] = $this->checkQBittorrentConnectionAction->handle($this->qBittorrentConnection($data));
        }

        if (!$data->downloadPath instanceof Optional) {
            $checks[SettingKey::DownloadPath->value] = $this->checkMediaFolderPathAction->handle($data->downloadPath);
        }

        return $checks;
    }

    /**
     * The sent qBittorrent values, with the saved password when none was sent: the form never gets it back to resend.
     */
    private function qBittorrentConnection(CheckSetupSettingsData $data): QBittorrentConnectionData
    {
        $password = $data->qbittorrentPassword instanceof Optional
            ? Setting::query()->find(SettingKey::QBittorrentPassword->value)?->value
            : $data->qbittorrentPassword;

        return new QBittorrentConnectionData(
            (string) $data->qbittorrentUrl,
            $data->qbittorrentUsername instanceof Optional ? '' : (string) $data->qbittorrentUsername,
            (string) $password,
        );
    }
}
