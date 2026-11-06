<?php

namespace App\Http\Resources\Dashboard;

use App\Dtos\Dashboard\DashboardStatsData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DashboardStatsData */
class DashboardStatsResource extends JsonResource
{
    /**
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        return [
            'shows_count' => $this->showsCount,
            'shows_added_last_7_days' => $this->showsAddedLast7Days,
            'shows_added_last_30_days' => $this->showsAddedLast30Days,
            'episodes_count' => $this->episodesCount,
            'episodes_missing_video_file_count' => $this->episodesMissingVideoFileCount,
            'websites_count' => $this->websitesCount,
            'users_count' => $this->usersCount,
        ];
    }
}
