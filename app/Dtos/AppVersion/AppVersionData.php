<?php

namespace App\Dtos\AppVersion;

use Spatie\LaravelData\Data;

class AppVersionData extends Data
{
    /**
     * The running backend version and the newest release of both repos. The frontend compares its own build version.
     */
    public function __construct(
        public string $backendVersion,
        public ?ReleaseData $latestBackendRelease,
        public ?ReleaseData $latestFrontendRelease,
    ) {}
}
