<?php

namespace App\Actions\ShowEntry;

use App\Actions\Episode\VideoFile\MoveVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Dtos\ShowEntry\UpdateShowEntryData;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowEntryAction
{
    use AsAction;

    public function __construct(
        private readonly MoveVideoFileAction $moveVideoFileAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    public function handle(UpdateShowEntryData $data): ShowEntry
    {
        return DB::transaction(function () use ($data): ShowEntry {
            $data->showEntry->loadMissing('show.titles', 'episodes');
            $oldEntryName = $data->showEntry->name;
            $episodeOldPaths = $data->showEntry->episodes
                ->filter(fn ($episode) => $episode->filename !== '')
                ->mapWithKeys(fn ($episode) => [$episode->id => $this->resolveEpisodeVideoPathAction->handle($episode, entryName: $oldEntryName)])
                ->all();

            $data->showEntry->fill(Arr::only($data->all(), $data->showEntry->getFillable()))->save();

            if ($data->showEntry->wasChanged('name')) {
                foreach ($data->showEntry->episodes as $episode) {
                    if ($episode->filename === '' || !isset($episodeOldPaths[$episode->id])) {
                        continue;
                    }

                    $oldPath = $episodeOldPaths[$episode->id];
                    $newPath = $this->resolveEpisodeVideoPathAction->handle($episode, entryName: $data->showEntry->name);

                    if ($oldPath !== $newPath) {
                        $this->moveVideoFileAction->handle($oldPath, $newPath);
                    }
                }
            }

            return $data->showEntry->fresh();
        });
    }
}
