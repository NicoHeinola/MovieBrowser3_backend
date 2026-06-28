<?php

namespace App\Dtos\Category;

use App\Models\Category\Category;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapOutputName(SnakeCaseMapper::class)]
class UpdateCategoryData extends Data
{
    public function __construct(
        public Category $category,
        public string|Optional $name,
        public string|Optional $value,
        public ?string $icon,
    ) {}
}
