<?php

namespace App\Actions\Episode;

use App\Actions\Episode\VideoFile\Path\ResolveEpisodeVideoPathAction;
use App\Actions\Episode\VideoFile\RenameVideoFileAction;
use App\Actions\Episode\VideoFile\StoreUploadedVideoAction;
use App\Dtos\Episode\UpdateEpisodeData;
use App\Models\Episode\Episode;
use App\Models\ShowWatchProgress\ShowWatchProgress;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateEpisodeAction
{
    use AsAction;

    public function __construct(
        private readonly StoreUploadedVideoAction $storeUploadedVideoAction,
        private readonly RenameVideoFileAction $renameVideoFileAction,
        private readonly ResolveEpisodeVideoPathAction $resolveEpisodeVideoPathAction,
    ) {}

    public function handle(UpdateEpisodeData $data): Episode
    {
        return DB::transaction(function () use ($data): Episode {
            $episode = $data->episode;
            $hadVideo = $episode->filename !== '';
            $oldPath = $this->resolveEpisodeVideoPathAction->handle($episode);

            $episode->fill(Arr::only($data->all(), $episode->getFillable()))->save();

            if ($episode->wasChanged('show_entry_id')) {
                $this->moveWatchProgressToNewEntry($episode);
            }

            if ($hadVideo) {
                // Its path holds the episode's name and entry, so it changes with them
                $newPath = $this->resolveEpisodeVideoPathAction->handle($episode);
                $this->renameVideoFileAction->handle($oldPath, $newPath);
            }

            // The upload copies and reads the file inside this transaction. It locks only this episode, and a failed
            // upload also undoes the other changes
            if ($data->file instanceof UploadedFile) {
                $this->storeUploadedVideoAction->handle($episode, $data->file);
            }

            return $episode->fresh();
        });
    }

    private function moveWatchProgressToNewEntry(Episode $episode): void
    {
        // The cached `entry` is still the old one; drop it so every path resolves into the new entry's folder
        $episode->unsetRelation('entry');

        // Keep "continue watching" pointing at this episode's new entry
        ShowWatchProgress::query()
            ->where('episode_id', $episode->id)
            ->update(['show_entry_id' => $episode->show_entry_id]);
    }
}
