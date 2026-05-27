<?php

namespace App\Dtos\ShowLink;

use App\Enums\ShowLinkType;
use App\Models\ShowLink\ShowLink;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
class ReciprocalShowLinkData extends Data
{
    public function __construct(
        public int $sourceShowId,
        public int $targetShowId,
        public ShowLinkType $type,
    ) {}

    public function matches(ShowLink $showLink): bool
    {
        return $showLink->source_show_id === $this->sourceShowId
            && $showLink->target_show_id === $this->targetShowId
            && $showLink->type === $this->type;
    }
}
