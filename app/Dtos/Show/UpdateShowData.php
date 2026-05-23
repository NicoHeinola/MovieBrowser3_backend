<?php

namespace App\Dtos\Show;

use App\Models\Show\Show;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapOutputName(SnakeCaseMapper::class)]
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
