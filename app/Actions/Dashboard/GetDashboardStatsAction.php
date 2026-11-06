<?php

namespace App\Actions\Dashboard;

use App\Dtos\Dashboard\DashboardStatsData;
use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\User\User;
use App\Models\Website\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDashboardStatsAction
{
    use AsAction;

    public function handle(): DashboardStatsData
    {
        return new DashboardStatsData(
            showsCount: Show::query()->count(),
            showsAddedLast7Days: Show::query()->where('created_at', '>=', now()->subDays(7))->count(),
            showsAddedLast30Days: Show::query()->where('created_at', '>=', now()->subDays(30))->count(),
            episodesCount: Episode::query()->count(),
            episodesMissingVideoFileCount: Episode::query()->where('has_video_file', false)->orWhere('filename', '')->count(),
            websitesCount: Website::query()->count(),
            usersCount: User::query()->count(),
        );
    }
}
