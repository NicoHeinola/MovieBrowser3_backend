<?php

namespace App\Dtos\LegacyImport;

use Spatie\LaravelData\Data;

/**
 * What importing one MovieBrowser2 show would do, worked out without writing anything.
 */
class LegacyShowImportCheckData extends Data
{
    /**
     * @param  list<string>  $missingFiles  source paths that don't exist; their episodes are imported without a video
     * @param  list<string>  $unknownUsernames  MovieBrowser2 users with a watch status but no MovieBrowser3 account
     */
    public function __construct(
        public int $legacyShowId,
        public string $title,
        public ?int $importedShowId,
        public int $fileCount,
        public int $totalBytes,
        public array $missingFiles,
        public array $unknownUsernames,
    ) {}
}
