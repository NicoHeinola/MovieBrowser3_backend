<?php

namespace App\Actions\Dashboard;

use App\Actions\Episode\VideoFile\ResolveFreeDiskSpaceAction;
use App\Actions\Episode\VideoFile\ResolveTotalDiskSpaceAction;
use App\Actions\Episode\VideoFile\ResolveVideoBasePathAction;
use App\Dtos\Dashboard\DashboardStatsData;
use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\User\User;
use App\Models\Website\Website;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDashboardStatsAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveVideoBasePathAction $resolveVideoBasePathAction,
        private readonly ResolveFreeDiskSpaceAction $resolveFreeDiskSpaceAction,
        private readonly ResolveTotalDiskSpaceAction $resolveTotalDiskSpaceAction,
    ) {}

    public function handle(): DashboardStatsData
    {
        $basePath = $this->resolveVideoBasePathAction->handle();
        // An unset or unmounted folder would report some other disk, so its space stays unknown
        $hasBaseFolder = $basePath !== '' && File::isDirectory($basePath);

        return new DashboardStatsData(
            showsCount: Show::query()->count(),
            showsAddedLast7Days: Show::query()->where('created_at', '>=', now()->subDays(7))->count(),
            showsAddedLast30Days: Show::query()->where('created_at', '>=', now()->subDays(30))->count(),
            episodesCount: Episode::query()->count(),
            episodesMissingVideoFileCount: Episode::query()->missingVideoFile()->count(),
            websitesCount: Website::query()->count(),
            usersCount: User::query()->count(),
            videoFilesTotalBytes: (int) Episode::query()->where('has_video_file', true)->sum('video_file_size'),
            diskTotalBytes: $hasBaseFolder ? $this->resolveTotalDiskSpaceAction->handle($basePath) : null,
            diskFreeBytes: $hasBaseFolder ? $this->resolveFreeDiskSpaceAction->handle($basePath) : null,
        );
    }
}
