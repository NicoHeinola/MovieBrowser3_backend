<?php

namespace App\Actions\ShowTitle;

use App\Actions\Episode\VideoFile\MoveVideoFileAction;
use App\Dtos\ShowTitle\UpdateShowTitleData;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowTitleAction
{
    use AsAction;

    public function __construct(private readonly MoveVideoFileAction $moveVideoFileAction) {}

    public function handle(UpdateShowTitleData $data): ShowTitle
    {
        $data->showTitle->loadMissing('show.titles', 'show.entries.episodes');
        $show = $data->showTitle->show;
        $oldPrimaryName = $show->titles->firstWhere('is_primary', true)?->title ?? (string) $show->id;

        $updatedTitle = DB::transaction(function () use ($data): ShowTitle {
            if ($data->isPrimary === true) {
                $data->showTitle->show->titles()
                    ->where('is_primary', true)
                    ->where('id', '!=', $data->showTitle->id)
                    ->update(['is_primary' => false]);
            }

            $data->showTitle->fill(Arr::only($data->all(), $data->showTitle->getFillable()))->save();

            return $data->showTitle->fresh();
        });

        $show = $updatedTitle->show->fresh()->load('titles', 'entries.episodes');
        $newPrimaryName = $show->titles->firstWhere('is_primary', true)?->title ?? (string) $show->id;

        if ($oldPrimaryName === $newPrimaryName) {
            return $updatedTitle;
        }

        foreach ($show->entries as $entry) {
            foreach ($entry->episodes as $episode) {
                if ($episode->filename === '') {
                    continue;
                }

                $oldPath = $episode->videoPath(null, $oldPrimaryName);
                $newPath = $episode->videoPath(null, $newPrimaryName);

                if ($oldPath !== $newPath) {
                    $this->moveVideoFileAction->handle($oldPath, $newPath, $episode->videoBasePath());
                }
            }
        }

        return $updatedTitle;
    }
}
