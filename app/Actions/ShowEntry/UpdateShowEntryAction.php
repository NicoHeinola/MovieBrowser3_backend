<?php

namespace App\Actions\ShowEntry;

use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\RenameVideoFileAction;
use App\Dtos\ShowEntry\UpdateShowEntryData;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowEntryAction
{
    use AsAction;

    public function __construct(
        private readonly RenameVideoFileAction $renameVideoFileAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    public function handle(UpdateShowEntryData $data): ShowEntry
    {
        return DB::transaction(function () use ($data): ShowEntry {
            $data->showEntry->loadMissing('show.titles', 'episodes');
            $oldEntryName = $data->showEntry->name;

            $data->showEntry->fill(Arr::only($data->all(), $data->showEntry->getFillable()))->save();

            $this->renameEntryVideos($data->showEntry, $oldEntryName);

            return $data->showEntry->fresh();
        });
    }

    /**
     * Gives the entry's videos their new paths: the entry's name is part of them. It renames nothing when the name is
     * the same.
     */
    private function renameEntryVideos(ShowEntry $showEntry, string $oldEntryName): void
    {
        $episodesWithVideo = $showEntry->episodes->where('filename', '!=', '');

        foreach ($episodesWithVideo as $episode) {
            $oldPath = $this->resolveEpisodeVideoPathAction->handle($episode, entryName: $oldEntryName);
            $newPath = $this->resolveEpisodeVideoPathAction->handle($episode, entryName: $showEntry->name);

            $this->renameVideoFileAction->handle($oldPath, $newPath);
        }
    }
}
