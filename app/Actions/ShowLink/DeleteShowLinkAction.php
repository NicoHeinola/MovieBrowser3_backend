<?php

namespace App\Actions\ShowLink;

use App\Actions\ShowLink\Concerns\InteractsWithReciprocalShowLinks;
use App\Models\ShowLink\ShowLink;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowLinkAction
{
    use AsAction;
    use InteractsWithReciprocalShowLinks;

    public function handle(ShowLink $showLink): void
    {
        DB::transaction(function () use ($showLink): void {
            $this->deleteReciprocalShowLink($showLink);
            $showLink->delete();
        });
    }
}
