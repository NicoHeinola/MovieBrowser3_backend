<?php

namespace App\Dtos\VideoRelocation;

use App\Enums\VideoRelocationErrorCode;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class VideoRelocationData extends Data
{
    public function __construct(
        public bool $running,
        public bool $reverting,
        public int $moved,
        public int $total,
        public ?string $fromPath,
        public ?string $toPath,
        public ?CarbonImmutable $finishedAt,
        public ?VideoRelocationErrorCode $errorCode,
        public ?string $errorPath,
    ) {}
}
