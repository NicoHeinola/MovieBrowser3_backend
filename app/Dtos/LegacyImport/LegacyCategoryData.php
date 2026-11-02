<?php

namespace App\Dtos\LegacyImport;

use Spatie\LaravelData\Data;

class LegacyCategoryData extends Data
{
    public function __construct(
        public string $name,
    ) {}
}
