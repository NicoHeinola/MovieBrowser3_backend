<?php

namespace App\Dtos\ShowTitle;

use App\Models\ShowTitle\ShowTitle;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapOutputName(SnakeCaseMapper::class)]
class UpdateShowTitleData extends Data
{
    public function __construct(
        public ShowTitle $showTitle,
        public string|Optional $title,
        public bool|Optional $isPrimary,
    ) {}
}
