<?php

namespace App\Dtos\Dashboard;

use Spatie\LaravelData\Data;

class DashboardStatsData extends Data
{
    public function __construct(
        public int $showsCount,
        public int $showsAddedLast7Days,
        public int $showsAddedLast30Days,
        public int $episodesCount,
        public int $episodesMissingVideoFileCount,
        public int $websitesCount,
        public int $usersCount,
        public int $videoFilesTotalBytes,
        public ?int $diskTotalBytes,
        public ?int $diskFreeBytes,
    ) {}
}
