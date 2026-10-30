<?php

namespace App\Actions\ShowSection;

use App\Models\ShowSection\ShowSection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowSectionAction
{
    use AsAction;

    public function handle(ShowSection $showSection): void
    {
        DB::transaction(function () use ($showSection): void {
            $showSection->delete();
        });
    }
}
