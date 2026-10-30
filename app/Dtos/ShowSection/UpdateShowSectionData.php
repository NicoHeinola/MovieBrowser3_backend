<?php

namespace App\Dtos\ShowSection;

use App\Models\ShowSection\ShowSection;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapOutputName(SnakeCaseMapper::class)]
class UpdateShowSectionData extends Data
{
    public function __construct(
        public ShowSection $showSection,
        public int|Optional $categoryId,
        public string|Optional|null $description,
        public int|Optional $sortOrder,
    ) {}
}
