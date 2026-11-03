<?php

namespace App\Dtos\AppVersion;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class AppVersionData extends Data
{
    public function __construct(
        public string $backendVersion,
        public ?ReleaseData $latestBackendRelease,
        public ?ReleaseData $latestFrontendRelease,
        public CarbonImmutable $checkedAt,
    ) {}
}
