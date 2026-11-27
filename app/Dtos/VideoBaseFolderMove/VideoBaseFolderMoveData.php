<?php

namespace App\Dtos\VideoBaseFolderMove;

use App\Enums\VideoBaseFolderMoveErrorCode;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class VideoBaseFolderMoveData extends Data
{
    public function __construct(
        public bool $running,
        public bool $reverting,
        public int $moved,
        public int $total,
        public ?string $fromPath,
        public ?string $toPath,
        public ?CarbonImmutable $finishedAt,
        public ?VideoBaseFolderMoveErrorCode $errorCode,
        public ?string $errorPath,
    ) {}
}
