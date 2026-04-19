<?php

namespace App\Dtos\Show;

use Spatie\LaravelData\Data;

class CreateShowData extends Data
{
    public function __construct(
        public string $bannerUrl,
        public string $cardImageUrl,
        public ?string $previewUrl,
        public ?string $description,
    ) {}
}
