<?php

namespace App\Dtos\Show;

use App\Models\Show\Show;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateShowData extends Data
{
    public function __construct(
        public Show $show,
        public string|Optional $bannerUrl,
        public string|Optional $cardImageUrl,
        public string|Optional|null $previewUrl,
        public string|Optional|null $description,
    ) {}
}
