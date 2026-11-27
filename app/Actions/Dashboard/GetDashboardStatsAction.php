<?php

namespace App\Actions\Dashboard;

use App\Actions\Episode\VideoFile\Path\ResolveVideoBaseFolderAction;
use App\Actions\File\ResolveDiskSpaceAction;
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
        private readonly ResolveVideoBaseFolderAction $resolveVideoBaseFolderAction,
        private readonly ResolveDiskSpaceAction $resolveDiskSpaceAction,
    ) {}

    public function handle(): DashboardStatsData
    {
        $videoBaseFolder = $this->resolveVideoBaseFolderAction->handle();
        // An unset or unmounted folder would report some other disk, so its space stays unknown
        $hasVideoBaseFolder = $videoBaseFolder !== '' && File::isDirectory($videoBaseFolder);
        $diskSpace = null;

        if ($hasVideoBaseFolder) {
            $diskSpace = $this->resolveDiskSpaceAction->handle($videoBaseFolder);
        }

        return new DashboardStatsData(
            showsCount: Show::query()->count(),
            showsAddedLast7Days: Show::query()->where('created_at', '>=', now()->subDays(7))->count(),
            showsAddedLast30Days: Show::query()->where('created_at', '>=', now()->subDays(30))->count(),
            episodesCount: Episode::query()->count(),
            episodesMissingVideoFileCount: Episode::query()->missingVideoFile()->count(),
            websitesCount: Website::query()->count(),
            usersCount: User::query()->count(),
            videoFilesTotalBytes: (int) Episode::query()->where('has_video_file', true)->sum('video_file_size'),
            videoFilesTotalSeconds: (int) Episode::query()->where('has_video_file', true)->sum('video_duration_seconds'),
            diskTotalBytes: $diskSpace?->totalBytes,
            diskFreeBytes: $diskSpace?->freeBytes,
        );
    }
}
