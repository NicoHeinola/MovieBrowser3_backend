<?php

namespace App\Dtos\ShowTitle;

use App\Models\ShowTitle\ShowTitle;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateShowTitleData extends Data
{
    public function __construct(
        public ShowTitle $showTitle,
        public string|Optional $title,
        public bool|Optional $isPrimary,
    ) {}
}
