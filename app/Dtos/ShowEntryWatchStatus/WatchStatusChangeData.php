<?php

namespace App\Dtos\ShowEntryWatchStatus;

use App\Enums\WatchStatus;
use Spatie\LaravelData\Data;

/**
 * A change of a user's watch status on one entry, as sent through the watch-progress stream; null status means cleared.
 */
class WatchStatusChangeData extends Data
{
    public function __construct(
        public int $showId,
        public int $showEntryId,
        public ?WatchStatus $status,
    ) {}
}
