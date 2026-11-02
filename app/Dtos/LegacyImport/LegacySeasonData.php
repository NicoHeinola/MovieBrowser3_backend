<?php

namespace App\Dtos\LegacyImport;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class LegacySeasonData extends Data
{
    /**
     * @param  array<int, LegacyEpisodeData>  $episodes
     */
    public function __construct(
        public int $id,
        public ?string $title,
        public int $number,
        #[DataCollectionOf(LegacyEpisodeData::class)]
        public array $episodes,
    ) {}
}
