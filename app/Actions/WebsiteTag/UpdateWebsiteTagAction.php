<?php

namespace App\Actions\WebsiteTag;

use App\Dtos\WebsiteTag\UpdateWebsiteTagData;
use App\Models\WebsiteTag\WebsiteTag;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateWebsiteTagAction
{
    use AsAction;

    public function handle(UpdateWebsiteTagData $data): WebsiteTag
    {
        return DB::transaction(function () use ($data): WebsiteTag {
            $data->websiteTag->fill(Arr::only($data->all(), $data->websiteTag->getFillable()))->save();

            return $data->websiteTag->fresh();
        });
    }
}
