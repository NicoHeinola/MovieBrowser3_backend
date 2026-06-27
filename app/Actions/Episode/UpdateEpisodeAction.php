<?php

namespace App\Actions\Episode;

use App\Actions\Episode\VideoFile\DeleteVideoFileAction;
use App\Actions\Episode\VideoFile\MoveVideoFileAction;
use App\Actions\Episode\VideoFile\StoreVideoFileAction;
use App\Dtos\Episode\UpdateEpisodeData;
use App\Models\Episode\Episode;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class UpdateEpisodeAction
{
    use AsAction;

    public function __construct(
        private readonly StoreVideoFileAction $storeVideoFileAction,
        private readonly MoveVideoFileAction $moveVideoFileAction,
        private readonly DeleteVideoFileAction $deleteVideoFileAction,
    ) {}

    public function handle(UpdateEpisodeData $data): Episode
    {
        $oldPath = $data->episode->videoPath();
        $oldFilename = $data->episode->filename;

        $data->episode->fill(Arr::only($data->all(), $data->episode->getFillable()))->save();

        // If a new video file is provided, store it and delete the old one if the path has changed
        if (!($data->file instanceof Optional)) {
            $episode = $this->storeVideoFileAction->handle($data->episode, $data->file);

            $newPath = $episode->videoPath();

            if ($oldPath !== $newPath) {
                $this->deleteVideoFileAction->handle(
                    $oldPath,
                    $episode->videoBasePath(),
                );
            }

            return $episode->fresh();
        }

        // Rename the video file if the episode name has changed and there is an existing video file
        if ($data->episode->wasChanged('name') && $oldFilename !== '') {
            $newPath = $data->episode->videoPath();

            if ($oldPath !== $newPath) {
                $this->moveVideoFileAction->handle(
                    $oldPath,
                    $newPath,
                    $data->episode->videoBasePath(),
                );
            }
        }

        return $data->episode->fresh();
    }
}
