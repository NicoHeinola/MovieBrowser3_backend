<?php

namespace App\Dtos\ShowEntry;

use App\Enums\ShowEntryType;
use App\Models\ShowEntry\ShowEntry;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateShowEntryData extends Data
{
    public function __construct(
        public ShowEntry $showEntry,
        public ShowEntryType|Optional $type,
        public string|Optional $name,
        public int|Optional $sortOrder,
    ) {}
}
