<?php

namespace App\Actions\ShowEntry;

use App\Models\ShowEntry\ShowEntry;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowEntryAction
{
    use AsAction;

    public function handle(ShowEntry $showEntry): void
    {
        $showEntry->delete();
    }
}
