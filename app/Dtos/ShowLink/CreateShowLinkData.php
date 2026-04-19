<?php

namespace App\Dtos\ShowLink;

use App\Enums\ShowLinkType;
use Spatie\LaravelData\Data;

class CreateShowLinkData extends Data
{
    public function __construct(
        public int $targetShowId,
        public ShowLinkType $type,
    ) {}
}
