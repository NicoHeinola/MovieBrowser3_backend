<?php

namespace App\Actions\Website;

use App\Dtos\Website\UpdateWebsiteData;
use App\Models\Website\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

class UpdateWebsiteAction
{
    use AsAction;

    public function handle(UpdateWebsiteData $data): Website
    {
        return DB::transaction(function () use ($data): Website {
            $data->website->fill(Arr::only($data->all(), $data->website->getFillable()))->save();

            if (!$data->tags instanceof Optional) {
                SyncWebsiteTagsAction::run($data->website, $data->tags);
            }

            return $data->website->fresh('tags');
        });
    }
}
