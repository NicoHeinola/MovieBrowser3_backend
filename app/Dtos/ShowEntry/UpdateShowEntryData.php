<?php

namespace App\Dtos\ShowEntry;

use App\Enums\ShowEntryType;
use App\Models\ShowEntry\ShowEntry;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapOutputName(SnakeCaseMapper::class)]
class UpdateShowEntryData extends Data
{
    public function __construct(
        public ShowEntry $showEntry,
        public ShowEntryType|Optional $type,
        public string|Optional $name,
        public int|Optional $sortOrder,
    ) {}
}
