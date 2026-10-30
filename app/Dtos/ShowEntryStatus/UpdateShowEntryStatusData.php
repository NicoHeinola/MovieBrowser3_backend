<?php

namespace App\Dtos\ShowEntryStatus;

use App\Enums\ShowWatchStatus;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use Spatie\LaravelData\Data;

class UpdateShowEntryStatusData extends Data
{
    public function __construct(
        public ShowEntry $showEntry,
        public User $user,
        public ShowWatchStatus $status,
    ) {}
}
