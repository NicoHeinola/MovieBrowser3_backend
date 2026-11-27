<?php

namespace App\Actions\ShowTitle;

use App\Actions\Episode\VideoFile\Path\ResolveShowFolderNameAction;
use App\Actions\Show\RenameShowVideosAction;
use App\Dtos\ShowTitle\CreateShowTitleData;
use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowTitleAction
{
    use AsAction;

    public function __construct(
        private readonly RenameShowVideosAction $renameShowVideosAction,
        private readonly ResolveShowFolderNameAction $resolveShowFolderNameAction,
    ) {}

    public function handle(Show $show, CreateShowTitleData $data): ShowTitle
    {
        $oldFolderName = $this->resolveShowFolderNameAction->handle($show);

        return DB::transaction(function () use ($show, $data, $oldFolderName): ShowTitle {
            if ($data->isPrimary) {
                $show->titles()->where('is_primary', true)->update(['is_primary' => false]);
            }

            $createdTitle = $show->titles()->create([
                'title' => $data->title,
                'is_primary' => $data->isPrimary,
            ]);

            $this->renameShowVideosAction->handle($show->fresh(), $oldFolderName);

            return $createdTitle;
        });
    }
}
