<?php

namespace App\Actions\ShowTitle;

use App\Actions\Episode\VideoFile\ResolveShowVideoFolderNameAction;
use App\Actions\Show\RelocateShowVideosAction;
use App\Dtos\ShowTitle\CreateShowTitleData;
use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowTitleAction
{
    use AsAction;

    public function __construct(
        private readonly RelocateShowVideosAction $relocateShowVideosAction,
        private readonly ResolveShowVideoFolderNameAction $resolveShowVideoFolderNameAction,
    ) {}

    public function handle(Show $show, CreateShowTitleData $data): ShowTitle
    {
        $oldFolderName = $this->resolveShowVideoFolderNameAction->handle($show);

        return DB::transaction(function () use ($show, $data, $oldFolderName): ShowTitle {
            if ($data->isPrimary) {
                $show->titles()->where('is_primary', true)->update(['is_primary' => false]);
            }

            $createdTitle = $show->titles()->create([
                'title' => $data->title,
                'is_primary' => $data->isPrimary,
            ]);

            $this->relocateShowVideosAction->handle($show->fresh(), $oldFolderName);

            return $createdTitle;
        });
    }
}
