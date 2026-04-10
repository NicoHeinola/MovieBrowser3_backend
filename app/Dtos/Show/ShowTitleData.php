<?php

namespace App\Dtos\Show;

use Spatie\LaravelData\Data;

class ShowTitleData extends Data
{
    public function __construct(
        public string $title,
        public bool $isPrimary,
    ) {}
}
