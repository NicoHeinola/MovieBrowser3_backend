<?php

namespace App\Actions\Setup;

use App\Dtos\Setup\SetupCheckData;
use App\Enums\SetupCheckCode;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckMediaFolderPathAction
{
    use AsAction;

    /**
     * A folder the app writes to (the video base folder, the download folder) must be visible here (inside a mounted
     * media folder in prod, so VLC and the download client see it too) and writable, or creatable when it doesn't exist
     * yet.
     */
    public function handle(?string $path): SetupCheckData
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

        // A folder that doesn't exist yet is created when it's first written to: its nearest existing parent must allow
        // that
        $parent = dirname($path);

        while (!file_exists($parent)) {
            $parent = dirname($parent);
        }

        return is_dir($parent) && is_writable($parent)
            ? SetupCheckData::passed()
            : SetupCheckData::failed(SetupCheckCode::PathNotCreatable, ['parent' => $parent]);
    }
}
