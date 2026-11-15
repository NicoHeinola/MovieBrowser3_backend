<?php

namespace App\Dtos\Backup;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class BackupFileData extends Data
{
    public function __construct(
        public string $name,
        public string $path,
        public int $size,
        public CarbonImmutable $createdAt,
    ) {}
}
