<?php

namespace App\Dtos\WebsiteTag;

use App\Models\WebsiteTag\WebsiteTag;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapOutputName(SnakeCaseMapper::class)]
class UpdateWebsiteTagData extends Data
{
    public function __construct(
        public WebsiteTag $websiteTag,
        public string|Optional $name,
    ) {}
}
