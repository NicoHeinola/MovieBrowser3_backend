<?php

namespace App\Dtos\ShowLink;

use App\Enums\ShowLinkType;
use App\Models\ShowLink\ShowLink;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateShowLinkData extends Data
{
    public function __construct(
        public ShowLink $showLink,
        public int|Optional $targetShowId,
        public ShowLinkType|Optional $type,
    ) {}
}
