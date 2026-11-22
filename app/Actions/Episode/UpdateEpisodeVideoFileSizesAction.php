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
     * A missing or replaced file forgets its length, which no longer fits; a first measurement keeps it.
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

                    // A stored size that changes means another file; none stored yet is just a first measurement
                    [$stale, $measured] = $group->partition(fn (Episode $episode): bool => $size === null || $episode->video_file_size !== null);

                    $this->update($measured, $size);
                    // Leaving the queue too, so a scan reading the old file doesn't store its length
                    $this->update($stale, $size, ['video_duration_seconds' => null, 'video_duration_queued_at' => null]);
                });
        });
    }

    /**
     * @param  Collection<int, Episode>  $episodes
     * @param  array<string, null>  $forget
     */
    private function update(Collection $episodes, ?int $size, array $forget = []): void
    {
        if ($episodes->isEmpty()) {
            return;
        }

        Episode::query()->whereKey($episodes->pluck('id'))->update([
            'has_video_file' => $size !== null,
            'video_file_size' => $size,
            ...$forget,
        ]);
    }
}
