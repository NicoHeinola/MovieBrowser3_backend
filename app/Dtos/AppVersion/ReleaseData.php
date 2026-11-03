<?php

namespace App\Dtos\AppVersion;

use Spatie\LaravelData\Data;

class ReleaseData extends Data
{
    public function __construct(
        public string $version,
        public string $url,
    ) {}
}
