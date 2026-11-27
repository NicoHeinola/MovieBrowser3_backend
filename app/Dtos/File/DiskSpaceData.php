<?php

namespace App\Dtos\File;

use Spatie\LaravelData\Data;

class DiskSpaceData extends Data
{
    public function __construct(
        public int $freeBytes,
        public int $totalBytes,
    ) {}
}
