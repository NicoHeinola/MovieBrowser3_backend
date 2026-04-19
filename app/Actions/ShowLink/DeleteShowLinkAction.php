<?php

namespace App\Actions\ShowLink;

use App\Models\ShowLink\ShowLink;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowLinkAction
{
    use AsAction;

    public function handle(ShowLink $showLink): void
    {
        $showLink->delete();
    }
}
