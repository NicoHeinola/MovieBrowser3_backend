<?php

namespace App\Dtos\WebsiteTag;

use Spatie\LaravelData\Data;

class CreateWebsiteTagData extends Data
{
    public function __construct(
        public string $name,
    ) {}
}
