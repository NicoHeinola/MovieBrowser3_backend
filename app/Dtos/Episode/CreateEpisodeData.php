<?php

namespace App\Dtos\Episode;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class CreateEpisodeData extends Data
{
    public function __construct(
        public string $name,
        public UploadedFile|Optional $file,
        public int $sequenceNumber,
    ) {}
}
