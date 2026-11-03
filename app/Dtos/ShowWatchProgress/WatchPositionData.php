<?php

namespace App\Dtos\ShowWatchProgress;

use Spatie\LaravelData\Data;

/**
 * How far into an episode the user is and how long it is, in whole seconds; null while VLC hasn't reported it.
 */
class WatchPositionData extends Data
{
    public function __construct(
        public ?int $positionSeconds = null,
        public ?int $durationSeconds = null,
    ) {}
}
