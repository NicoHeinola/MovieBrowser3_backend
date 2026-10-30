<?php

namespace App\Dtos\ShowEntryWatchStatus;

use App\Enums\WatchStatus;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use Spatie\LaravelData\Data;

class UpdateShowEntryWatchStatusData extends Data
{
    public function __construct(
        public ShowEntry $showEntry,
        public User $user,
        public WatchStatus $status,
    ) {}
}
