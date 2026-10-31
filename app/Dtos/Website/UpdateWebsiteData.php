<?php

namespace App\Dtos\Website;

use App\Models\Website\Website;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapOutputName(SnakeCaseMapper::class)]
class UpdateWebsiteData extends Data
{
    /**
     * @param  array<int, string>|Optional  $tags
     */
    public function __construct(
        public Website $website,
        public string|Optional $url,
        public string|Optional|null $title,
        public string|Optional|null $icon,
        public string|Optional|null $description,
        public array|Optional $tags,
    ) {}
}
