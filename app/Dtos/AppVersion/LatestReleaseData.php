<?php

namespace App\Dtos\AppVersion;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class LatestReleaseData extends Data
{
    /**
     * A repo's newest release as of the last check, or no release when that check failed.
     */
    public function __construct(
        public ?ReleaseData $release,
        public CarbonImmutable $checkedAt,
    ) {}
}
