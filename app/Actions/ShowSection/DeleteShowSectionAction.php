<?php

namespace App\Actions\ShowSection;

use App\Models\ShowSection\ShowSection;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowSectionAction
{
    use AsAction;

    public function handle(ShowSection $showSection): void
    {
        $showSection->delete();
    }
}
