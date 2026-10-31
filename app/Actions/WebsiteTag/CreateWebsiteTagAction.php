<?php

namespace App\Actions\WebsiteTag;

use App\Dtos\WebsiteTag\CreateWebsiteTagData;
use App\Models\WebsiteTag\WebsiteTag;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateWebsiteTagAction
{
    use AsAction;

    public function handle(CreateWebsiteTagData $data): WebsiteTag
    {
        return DB::transaction(function () use ($data): WebsiteTag {
            return WebsiteTag::query()->create([
                'name' => $data->name,
            ]);
        });
    }
}
