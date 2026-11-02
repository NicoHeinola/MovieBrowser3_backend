<?php

namespace App\Dtos\LegacyImport;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * One show from the MovieBrowser2 export endpoint. Fields MovieBrowser3 has no use for are ignored.
 */
class LegacyShowData extends Data
{
    /**
     * @param  array<int, LegacyCategoryData>  $categories
     * @param  array<int, LegacySeasonData>  $seasons
     * @param  array<int, LegacyUserShowStatusData>  $userShowStatuses
     * @param  array<int, LegacyUserWatchSeasonData>  $userWatchSeasons
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public ?string $image,
        #[DataCollectionOf(LegacyCategoryData::class)]
        public array $categories,
        #[DataCollectionOf(LegacySeasonData::class)]
        public array $seasons,
        #[DataCollectionOf(LegacyUserShowStatusData::class)]
        public array $userShowStatuses = [],
        #[DataCollectionOf(LegacyUserWatchSeasonData::class)]
        public array $userWatchSeasons = [],
    ) {}
}
