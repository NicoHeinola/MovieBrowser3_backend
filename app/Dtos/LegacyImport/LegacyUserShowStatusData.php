<?php

namespace App\Dtos\LegacyImport;

use Spatie\LaravelData\Data;

class LegacyUserShowStatusData extends Data
{
    public function __construct(
        public string $username,
        public string $status,
    ) {}
}
