<?php

namespace App\Dtos\Show;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class CreateShowData extends Data
{
    /**
     * @param  array<int, ShowTitleData>  $titles
     */
    public function __construct(
        public string $bannerUrl,
        public string $cardImageUrl,
        public ?string $previewUrl,
        #[DataCollectionOf(ShowTitleData::class)]
        public array $titles,
    ) {}
}
