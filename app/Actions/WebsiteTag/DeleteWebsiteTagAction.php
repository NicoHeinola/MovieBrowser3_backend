<?php

namespace App\Actions\WebsiteTag;

use App\Models\WebsiteTag\WebsiteTag;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteWebsiteTagAction
{
    use AsAction;

    public function handle(WebsiteTag $websiteTag): void
    {
        DB::transaction(function () use ($websiteTag): void {
            $websiteTag->delete();
        });
    }
}
