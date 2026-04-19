<?php

namespace App\Actions\ShowTitle;

use App\Dtos\ShowTitle\CreateShowTitleData;
use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowTitleAction
{
    use AsAction;

    public function handle(Show $show, CreateShowTitleData $data): ShowTitle
    {
        return DB::transaction(function () use ($show, $data): ShowTitle {
            if ($data->isPrimary) {
                $show->titles()->where('is_primary', true)->update(['is_primary' => false]);
            }

            return $show->titles()->create([
                'title' => $data->title,
                'is_primary' => $data->isPrimary,
            ]);
        });
    }
}
