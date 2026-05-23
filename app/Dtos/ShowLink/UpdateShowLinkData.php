<?php

namespace App\Dtos\ShowLink;

use App\Enums\ShowLinkType;
use App\Models\ShowLink\ShowLink;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapOutputName(SnakeCaseMapper::class)]
class UpdateShowLinkData extends Data
{
    public function __construct(
        public ShowLink $showLink,
        public int|Optional $targetShowId,
        public ShowLinkType|Optional $type,
    ) {}
}
