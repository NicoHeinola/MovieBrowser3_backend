<?php

namespace App\Dtos\VideoFile;

use Spatie\LaravelData\Data;

class ShowVideoFileCheckData extends Data
{
    /**
     * What a check of one show's video files found.
     *
     * @param  int  $missingVideoFilesCount  Episodes whose file is gone
     * @param  int  $unknownVideoDurationsCount  Episodes whose file is there but its length can't be read
     */
    public function __construct(
        public int $missingVideoFilesCount,
        public int $unknownVideoDurationsCount,
    ) {}
}
