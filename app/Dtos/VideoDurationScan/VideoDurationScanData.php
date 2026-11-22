<?php

namespace App\Dtos\VideoDurationScan;

use App\Enums\VideoDurationScanErrorCode;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class VideoDurationScanData extends Data
{
    public function __construct(
        public bool $running,
        public int $read,
        public int $total,
        public ?CarbonImmutable $finishedAt,
        public ?VideoDurationScanErrorCode $errorCode,
        public int $unknownDurationsCount,
    ) {}
}
