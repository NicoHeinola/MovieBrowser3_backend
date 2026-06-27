<?php

namespace App\Actions\ShowEntry;

use App\Actions\Episode\VideoFile\MoveVideoFileAction;
use App\Dtos\ShowEntry\UpdateShowEntryData;
use App\Models\ShowEntry\ShowEntry;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowEntryAction
{
    use AsAction;

    public function __construct(private readonly MoveVideoFileAction $moveVideoFileAction) {}

    public function handle(UpdateShowEntryData $data): ShowEntry
    {
        $data->showEntry->loadMissing('show.titles', 'episodes');
        $oldEntryName = $data->showEntry->name;
        $episodeOldPaths = $data->showEntry->episodes
            ->filter(fn ($episode) => $episode->filename !== '')
            ->mapWithKeys(fn ($episode) => [$episode->id => $episode->videoPath(null, null, $oldEntryName)])
            ->all();

        $data->showEntry->fill(Arr::only($data->all(), $data->showEntry->getFillable()))->save();

        if ($data->showEntry->wasChanged('name')) {
            foreach ($data->showEntry->episodes as $episode) {
                if ($episode->filename === '' || !isset($episodeOldPaths[$episode->id])) {
                    continue;
                }

                $oldPath = $episodeOldPaths[$episode->id];
                $newPath = $episode->videoPath(null, null, $data->showEntry->name);

                if ($oldPath !== $newPath) {
                    $this->moveVideoFileAction->handle($oldPath, $newPath, $episode->videoBasePath());
                }
            }
        }

        return $data->showEntry->fresh();
    }
}
