<?php

namespace App\Dtos\Episode;

use App\Models\Episode\Episode;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapOutputName(SnakeCaseMapper::class)]
class UpdateEpisodeData extends Data
{
    public function __construct(
        public Episode $episode,
        public string|Optional $name,
        public string|Optional $filename,
        public int|Optional $sequenceNumber,
    ) {}
}
