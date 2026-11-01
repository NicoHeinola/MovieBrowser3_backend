<?php

namespace App\Actions\Setup;

use App\Actions\EpisodePlayback\CheckVlcBinaryAction;
use App\Dtos\Setup\CheckSetupSettingsData;
use App\Dtos\Setup\SetupCheckData;
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
            $checks['video_base_path'] = SetupCheckData::fromProblem($this->videoBasePathProblem($data->videoBasePath));
        }

        if (!$data->vlcMediaPlayerPath instanceof Optional) {
            $checks['vlc_media_player_path'] = SetupCheckData::fromProblem($this->checkVlcBinaryAction->handle($data->vlcMediaPlayerPath));
        }

        return $checks;
    }

    /**
     * The video folder must be visible here (inside a mounted media folder in prod, so VLC sees it too) and writable,
     * or creatable when it doesn't exist yet.
     */
    private function videoBasePathProblem(?string $path): ?string
    {
        if (blank($path)) {
            return 'Enter a folder.';
        }

        $path = rtrim($path, '/') ?: '/';

        if (!str_starts_with($path, '/')) {
            return 'Use a full path, starting with /.';
        }

        if (in_array('..', explode('/', $path), true)) {
            return 'Use a path without "..".';
        }

        $mediaFolders = config('deploy.media_folders');

        if ($mediaFolders !== [] && !collect($mediaFolders)->contains(fn (string $folder): bool => $path === $folder || Str::startsWith($path, "{$folder}/"))) {
            return 'This folder is outside the media folders ('.implode(', ', $mediaFolders).'), so MovieBrowser3 and VLC can\'t see it. Pick a folder inside one, or add a media folder by running the installer again.';
        }

        if (file_exists($path)) {
            if (!is_dir($path)) {
                return 'This is a file, not a folder.';
            }

            return is_writable($path) ? null : 'MovieBrowser3 can\'t write to this folder.';
        }

        // A folder that doesn't exist yet is created on the first upload: its nearest existing parent must allow that
        $parent = dirname($path);

        while (!file_exists($parent)) {
            $parent = dirname($parent);
        }

        return is_dir($parent) && is_writable($parent) ? null : "MovieBrowser3 can't create this folder in {$parent}.";
    }
}
