<?php

namespace App\Dtos\LegacyImport;

use Spatie\LaravelData\Data;

class LegacyEpisodeData extends Data
{
    public function __construct(
        public int $id,
        public ?string $title,
        public int $number,
        /** `episode`, `ova` or `movie` */
        public string $type,
        public ?string $filename,
        public ?LegacyFilePathData $filePath,
    ) {}
}
