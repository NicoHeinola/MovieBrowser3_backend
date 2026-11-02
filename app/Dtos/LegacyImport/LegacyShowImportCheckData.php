<?php

namespace App\Dtos\LegacyImport;

use Spatie\LaravelData\Data;

/**
 * What importing one MovieBrowser2 show would do, worked out without writing anything.
 */
class LegacyShowImportCheckData extends Data
{
    /**
     * @param  int  $importedVideoCount  episodes with a video in the show it was imported as; fewer than `$fileCount` means
     *                                   videos were lost on the way (an import before missing videos failed the show)
     * @param  list<string>  $missingFiles  source paths that don't exist; their episodes are imported without a video
     * @param  list<string>  $unknownUsernames  MovieBrowser2 users with a watch status but no MovieBrowser3 account
     */
    public function __construct(
        public int $legacyShowId,
        public string $title,
        public ?int $importedShowId,
        public int $importedVideoCount,
        public int $fileCount,
        public int $totalBytes,
        public array $missingFiles,
        public array $unknownUsernames,
    ) {}

    /**
     * Imported, but with fewer videos than the originals.
     */
    public function isIncomplete(): bool
    {
        return $this->importedShowId !== null && $this->importedVideoCount < $this->fileCount;
    }
}
