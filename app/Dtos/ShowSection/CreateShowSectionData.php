<?php

namespace App\Dtos\ShowSection;

use Spatie\LaravelData\Data;

class CreateShowSectionData extends Data
{
    public function __construct(
        public int $categoryId,
        public ?string $description,
        public int $sortOrder,
    ) {}
}
