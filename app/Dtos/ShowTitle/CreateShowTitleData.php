<?php

namespace App\Dtos\ShowTitle;

use Spatie\LaravelData\Data;

class CreateShowTitleData extends Data
{
    public function __construct(
        public string $title,
        public bool $isPrimary,
    ) {}
}
