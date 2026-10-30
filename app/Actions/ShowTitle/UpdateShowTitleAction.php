<?php

namespace App\Actions\ShowTitle;

use App\Actions\Episode\VideoFile\ResolveShowVideoFolderNameAction;
use App\Actions\Show\RelocateShowVideosAction;
use App\Dtos\ShowTitle\UpdateShowTitleData;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShowTitleAction
{
    use AsAction;

    public function __construct(
        private readonly RelocateShowVideosAction $relocateShowVideosAction,
        private readonly ResolveShowVideoFolderNameAction $resolveShowVideoFolderNameAction,
    ) {}

    public function handle(UpdateShowTitleData $data): ShowTitle
    {
        $oldFolderName = $this->resolveShowVideoFolderNameAction->handle($data->showTitle->show);

        return DB::transaction(function () use ($data, $oldFolderName): ShowTitle {
            if ($data->isPrimary === true) {
                $data->showTitle->show->titles()
                    ->where('is_primary', true)
                    ->where('id', '!=', $data->showTitle->id)
                    ->update(['is_primary' => false]);
            }

            $data->showTitle->fill(Arr::only($data->all(), $data->showTitle->getFillable()))->save();

            $this->relocateShowVideosAction->handle($data->showTitle->show->fresh(), $oldFolderName);

            return $data->showTitle->fresh();
        });
    }
}
