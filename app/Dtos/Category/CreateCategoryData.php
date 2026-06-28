<?php

namespace App\Dtos\Category;

use Spatie\LaravelData\Data;

class CreateCategoryData extends Data
{
    public function __construct(
        public string $name,
        public string $value,
        public ?string $icon = null,
    ) {}
}
