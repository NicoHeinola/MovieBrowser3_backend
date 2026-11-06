<?php

namespace App\Dtos\VideoFile;

use App\Enums\VideoFileCheckErrorCode;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class VideoFileCheckData extends Data
{
    public function __construct(
        public bool $running,
        public int $checked,
        public int $total,
        public ?CarbonImmutable $finishedAt,
        public ?VideoFileCheckErrorCode $errorCode,
        public int $missingVideoFilesCount,
    ) {}
}
