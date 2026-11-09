<?php

namespace App\Actions\Episode;

use App\Actions\Episode\VideoFile\DeleteVideoFileAction;
use App\Actions\Episode\VideoFile\MoveVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\StoreVideoFileAction;
use App\Dtos\Episode\UpdateEpisodeData;
use App\Models\Episode\Episode;
use App\Models\ShowWatchProgress\ShowWatchProgress;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class UpdateEpisodeAction
{
    use AsAction;

    public function __construct(
        private readonly StoreVideoFileAction $storeVideoFileAction,
        private readonly MoveVideoFileAction $moveVideoFileAction,
        private readonly DeleteVideoFileAction $deleteVideoFileAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    public function handle(UpdateEpisodeData $data): Episode
    {
        return DB::transaction(function () use ($data): Episode {
            $oldPath = $this->resolveEpisodeVideoPathAction->handle($data->episode);
            $oldFilename = $data->episode->filename;

            $data->episode->fill(Arr::only($data->all(), $data->episode->getFillable()))->save();

            $movedEntry = $data->episode->wasChanged('show_entry_id');

            if ($movedEntry) {
                // The cached `entry` is still the old one; drop it so every path below resolves into the new entry's folder
                $data->episode->unsetRelation('entry');

                // Keep "continue watching" pointing at this episode's new entry
                ShowWatchProgress::query()
                    ->where('episode_id', $data->episode->id)
                    ->update(['show_entry_id' => $data->episode->show_entry_id]);
            }

            // If a new video file is provided, store it and delete the old one if the path has changed
            if (!($data->file instanceof Optional)) {
                $episode = $this->storeVideoFileAction->handle($data->episode, $data->file);

                $newPath = $this->resolveEpisodeVideoPathAction->handle($episode);

                if ($oldFilename !== '' && $oldPath !== $newPath) {
                    DB::afterCommit(fn () => $this->deleteVideoFileAction->handle($oldPath));
                }

                return $episode->fresh();
            }

            // Move the existing video file when its name or entry changed, both at once landing on one final path
            if (($movedEntry || $data->episode->wasChanged('name')) && $oldFilename !== '') {
                $newPath = $this->resolveEpisodeVideoPathAction->handle($data->episode);

                if ($oldPath !== $newPath) {
                    $this->moveVideoFileAction->handle($oldPath, $newPath);
                }
            }

            return $data->episode->fresh();
        });
    }
}
