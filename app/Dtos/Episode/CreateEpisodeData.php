<?php

namespace App\Dtos\Episode;

use Spatie\LaravelData\Data;

class CreateEpisodeData extends Data
{
    public function __construct(
        public string $name,
        public string $filename,
        public int $sequenceNumber,
    ) {}
}
