<?php

namespace App\Dtos\LegacyImport;

use Spatie\LaravelData\Data;

/**
 * The season a MovieBrowser2 user was on in a show.
 */
class LegacyUserWatchSeasonData extends Data
{
    public function __construct(
        public string $username,
        public int $seasonId,
    ) {}
}
