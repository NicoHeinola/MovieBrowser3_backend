<?php

namespace App\Actions\ShowTitle;

use App\Actions\Episode\VideoFile\MoveVideoFileAction;
use App\Dtos\ShowTitle\CreateShowTitleData;
use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowTitleAction
{
    use AsAction;

    public function __construct(private readonly MoveVideoFileAction $moveVideoFileAction) {}

    public function handle(Show $show, CreateShowTitleData $data): ShowTitle
    {
        $show->loadMissing('titles', 'entries.episodes');
        $oldPrimaryName = $show->titles->firstWhere('is_primary', true)?->title ?? (string) $show->id;

        $createdTitle = DB::transaction(function () use ($show, $data): ShowTitle {
            if ($data->isPrimary) {
                $show->titles()->where('is_primary', true)->update(['is_primary' => false]);
            }

            return $show->titles()->create([
                'title' => $data->title,
                'is_primary' => $data->isPrimary,
            ]);
        });

        $show = $show->fresh()->load('titles', 'entries.episodes');
        $newPrimaryName = $show->titles->firstWhere('is_primary', true)?->title ?? (string) $show->id;

        if ($oldPrimaryName === $newPrimaryName) {
            return $createdTitle;
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

        return $createdTitle;
    }
}
