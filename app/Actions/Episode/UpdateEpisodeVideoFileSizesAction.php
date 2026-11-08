<?php

namespace App\Actions\Episode;

use App\Models\Episode\Episode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateEpisodeVideoFileSizesAction
{
    use AsAction;

    /**
     * Stores measured video file sizes, keyed by episode id; null marks the file missing.
     * Only changed rows are written, one update per distinct size: every missing file shares one,
     * and found sizes only differ on the first measurement or after a file was replaced.
     *
     * @param  Collection<int, Episode>  $episodes
     * @param  Collection<int, int|null>  $sizes
     */
    public function handle(Collection $episodes, Collection $sizes): void
    {
        DB::transaction(function () use ($episodes, $sizes): void {
            $episodes
                ->reject(fn (Episode $episode): bool => $episode->has_video_file === ($sizes[$episode->id] !== null)
                    && $episode->video_file_size === $sizes[$episode->id])
                ->groupBy(fn (Episode $episode): string => (string) ($sizes[$episode->id] ?? 'missing'))
                ->each(function (Collection $group) use ($sizes): void {
                    $size = $sizes[$group->first()->id];

                    Episode::query()->whereKey($group->pluck('id'))->update([
                        'has_video_file' => $size !== null,
                        'video_file_size' => $size,
                    ]);
                });
        });
    }
}
