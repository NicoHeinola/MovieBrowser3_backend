<?php

namespace App\Actions\Website;

use App\Models\Website\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteWebsiteAction
{
    use AsAction;

    public function handle(Website $website): void
    {
        DB::transaction(function () use ($website): void {
            $website->delete();
        });
    }
}
