<?php

namespace App\Actions\Episode;

use App\Actions\Episode\VideoFile\DeleteVideoFileAction;
use App\Actions\Episode\VideoFile\MoveVideoFileAction;
use App\Actions\Episode\VideoFile\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\StoreVideoFileAction;
use App\Dtos\Episode\UpdateEpisodeData;
use App\Models\Episode\Episode;
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

            // If a new video file is provided, store it and delete the old one if the path has changed
            if (!($data->file instanceof Optional)) {
                $episode = $this->storeVideoFileAction->handle($data->episode, $data->file);

                $newPath = $this->resolveEpisodeVideoPathAction->handle($episode);

                if ($oldFilename !== '' && $oldPath !== $newPath) {
                    DB::afterCommit(fn () => $this->deleteVideoFileAction->handle($oldPath));
                }

                return $episode->fresh();
            }

            // Rename the video file if the episode name has changed and there is an existing video file
            if ($data->episode->wasChanged('name') && $oldFilename !== '') {
                $newPath = $this->resolveEpisodeVideoPathAction->handle($data->episode);

                if ($oldPath !== $newPath) {
                    $this->moveVideoFileAction->handle($oldPath, $newPath);
                }
            }

            return $data->episode->fresh();
        });
    }
}
