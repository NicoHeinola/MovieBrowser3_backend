<?php

namespace App\Dtos\EpisodePlayback;

use Spatie\LaravelData\Data;

/**
 * Where a VLC window we opened listens for web interface requests, and the password it was started with.
 */
class VlcConnectionData extends Data
{
    public function __construct(
        public int $port,
        public string $password,
    ) {}
}
