<?php

namespace App\Actions\Website;

use App\Dtos\Website\CreateWebsiteData;
use App\Models\Website\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateWebsiteAction
{
    use AsAction;

    public function handle(CreateWebsiteData $data): Website
    {
        return DB::transaction(function () use ($data): Website {
            $website = Website::query()->create([
                'url' => $data->url,
                'title' => $data->title,
                'icon' => $data->icon,
                'description' => $data->description,
            ]);

            $website->tags()->sync($data->tagIds);

            return $website->load('tags');
        });
    }
}
