<?php

namespace App\Actions\LegacyImport;

use App\Dtos\LegacyImport\LegacyEpisodeData;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveLegacyEpisodeSourcePathAction
{
    use AsAction;

    /**
     * Where a MovieBrowser2 episode's video is: its absolute path, or its relative path under `$sourceRoot`
     * when MovieBrowser2's folder is mounted somewhere else. Null when the episode has no video.
     */
    public function handle(LegacyEpisodeData $episode, ?string $sourceRoot = null): ?string
    {
        if ($episode->filePath === null || ($episode->filename ?? '') === '') {
            return null;
        }

        if ($sourceRoot === null) {
            return $episode->filePath->absolute;
        }

        return rtrim($sourceRoot, '/\\').'/'.ltrim($episode->filePath->relative, '/\\');
    }
}
