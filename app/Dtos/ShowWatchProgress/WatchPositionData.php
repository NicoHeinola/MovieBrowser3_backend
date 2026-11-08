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

    /**
     * Whether the position is within `watch_progress.finished_before_end_seconds` of the end, so the episode counts as
     * watched. Unknown values never count, and neither does a video no longer than that window: any position in it
     * would be "near the end".
     */
    public function isNearEnd(): bool
    {
        $window = (int) config('watch_progress.finished_before_end_seconds');

        return $this->positionSeconds !== null
            && $this->durationSeconds !== null
            && $this->durationSeconds > $window
            && $this->positionSeconds >= $this->durationSeconds - $window;
    }
}
