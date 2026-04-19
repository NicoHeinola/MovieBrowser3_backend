<?php

namespace App\Dtos\ShowEntry;

use App\Enums\ShowEntryType;
use Spatie\LaravelData\Data;

class CreateShowEntryData extends Data
{
    public function __construct(
        public ShowEntryType $type,
        public string $name,
        public int $sortOrder,
    ) {}
}
