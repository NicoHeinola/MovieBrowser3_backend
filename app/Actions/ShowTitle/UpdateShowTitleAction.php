<?php

namespace App\Actions\ShowTitle;

use App\Dtos\ShowTitle\UpdateShowTitleData;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class UpdateShowTitleAction
{
    use AsAction;

    public function handle(UpdateShowTitleData $data): ShowTitle
    {
        return DB::transaction(function () use ($data): ShowTitle {
            $attributes = [];

            if (!($data->title instanceof Optional)) {
                $attributes['title'] = $data->title;
            }

            if (!($data->isPrimary instanceof Optional)) {
                if ($data->isPrimary) {
                    $data->showTitle->show->titles()
                        ->where('is_primary', true)
                        ->where('id', '!=', $data->showTitle->id)
                        ->update(['is_primary' => false]);
                }

                $attributes['is_primary'] = $data->isPrimary;
            }

            if ($attributes !== []) {
                $data->showTitle->update($attributes);
            }

            return $data->showTitle->fresh();
        });
    }
}
