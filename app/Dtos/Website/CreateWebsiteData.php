<?php

namespace App\Dtos\Website;

use Spatie\LaravelData\Data;

class CreateWebsiteData extends Data
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public string $url,
        public ?string $title = null,
        public ?string $icon = null,
        public ?string $description = null,
        public array $tags = [],
    ) {}
}
