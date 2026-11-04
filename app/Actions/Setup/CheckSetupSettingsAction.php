<?php

namespace App\Actions\Setup;

use App\Actions\EpisodePlayback\CheckVlcBinaryAction;
use App\Dtos\Setup\CheckSetupSettingsData;
use App\Dtos\Setup\SetupCheckData;
use App\Enums\SetupCheckCode;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class CheckSetupSettingsAction
{
    use AsAction;

    public function __construct(
        private readonly CheckVlcBinaryAction $checkVlcBinaryAction,
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
            $checks['video_base_path'] = $this->checkVideoBasePath($data->videoBasePath);
        }

        if (!$data->vlcMediaPlayerPath instanceof Optional) {
            $checks['vlc_media_player_path'] = $this->checkVlcBinaryAction->handle($data->vlcMediaPlayerPath);
        }

        return $checks;
    }

    /**
     * The video folder must be visible here (inside a mounted media folder in prod, so VLC sees it too) and writable,
     * or creatable when it doesn't exist yet.
     */
    private function checkVideoBasePath(?string $path): SetupCheckData
    {
        if (blank($path)) {
            return SetupCheckData::failed(SetupCheckCode::PathRequired);
        }

        $path = rtrim($path, '/') ?: '/';

        if (!str_starts_with($path, '/')) {
            return SetupCheckData::failed(SetupCheckCode::PathNotAbsolute);
        }

        if (in_array('..', explode('/', $path), true)) {
            return SetupCheckData::failed(SetupCheckCode::PathHasParentSegment);
        }

        $mediaFolders = config('deploy.media_folders');

        if ($mediaFolders !== [] && !collect($mediaFolders)->contains(fn (string $folder): bool => $path === $folder || Str::startsWith($path, "{$folder}/"))) {
            return SetupCheckData::failed(SetupCheckCode::PathOutsideMediaFolders, ['folders' => implode(', ', $mediaFolders)]);
        }

        if (file_exists($path)) {
            if (!is_dir($path)) {
                return SetupCheckData::failed(SetupCheckCode::PathIsFile);
            }

            return is_writable($path) ? SetupCheckData::passed() : SetupCheckData::failed(SetupCheckCode::PathNotWritable);
        }

        // A folder that doesn't exist yet is created on the first upload: its nearest existing parent must allow that
        $parent = dirname($path);

        while (!file_exists($parent)) {
            $parent = dirname($parent);
        }

        return is_dir($parent) && is_writable($parent)
            ? SetupCheckData::passed()
            : SetupCheckData::failed(SetupCheckCode::PathNotCreatable, ['parent' => $parent]);
    }
}
