<?php

namespace App\Actions\Setup;

use App\Actions\Episode\VideoFile\CheckVideoBasePathValueAction;
use App\Actions\EpisodePlayback\CheckVlcBinaryAction;
use App\Dtos\Setup\CheckSetupSettingsData;
use App\Dtos\Setup\SetupCheckData;
use App\Enums\SettingKey;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class CheckSetupSettingsAction
{
    use AsAction;

    public function __construct(
        private readonly CheckVlcBinaryAction $checkVlcBinaryAction,
        private readonly CheckVideoBasePathValueAction $checkVideoBasePathValueAction,
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
            $checks[SettingKey::VideoBasePath->value] = $this->checkVideoBasePathValueAction->handle($data->videoBasePath);
        }

        if (!$data->vlcMediaPlayerPath instanceof Optional) {
            $checks[SettingKey::VlcMediaPlayerPath->value] = $this->checkVlcBinaryAction->handle($data->vlcMediaPlayerPath);
        }

        return $checks;
    }
}
