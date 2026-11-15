<?php

namespace App\Actions\VideoRelocation;

use App\Actions\Episode\VideoFile\RelocateEpisodeVideoAction;
use App\Models\Episode\Episode;
use App\Models\VideoRelocation\VideoRelocation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVideoRelocationProgressAction
{
    use AsAction;

    public function __construct(private readonly RelocateEpisodeVideoAction $relocateEpisodeVideoAction) {}

    /**
     * Moves the video of the next episode after `$afterId`: to `to_path`, or back to `from_path` while reverting.
     * Returns that episode's id to continue after, or null when no episode is left.
     */
    public function handle(VideoRelocation $relocation, int $afterId = 0): ?int
    {
        $episode = Episode::query()
            ->with('entry.show.titles')
            ->where('filename', '!=', '')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->first();

        if ($episode === null) {
            return null;
        }

        [$fromPath, $toPath] = $relocation->reverting
            ? [$relocation->to_path, $relocation->from_path]
            : [$relocation->from_path, $relocation->to_path];

        // Outside the transaction: a copy between disks can take minutes and must hold no lock
        $this->relocateEpisodeVideoAction->handle($episode, $fromPath, $toPath);

        DB::transaction(function () use ($relocation): void {
            $relocation->increment('moved');
        });

        return $episode->id;
    }
}
