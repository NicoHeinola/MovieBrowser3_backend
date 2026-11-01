<?php

namespace App\Dtos\Website;

use Spatie\LaravelData\Data;

class CreateWebsiteData extends Data
{
    /**
     * @param  array<int, int>  $tagIds
     */
    public function __construct(
        public string $url,
        public ?string $title = null,
        public ?string $icon = null,
        public ?string $description = null,
        public bool $isFavorite = false,
        public array $tagIds = [],
    ) {}
}
