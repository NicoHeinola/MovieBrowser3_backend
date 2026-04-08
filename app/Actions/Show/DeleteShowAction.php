<?php

namespace App\Actions\Show;

use App\Models\Show\Show;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShowAction
{
    use AsAction;

    public function handle(Show $show): void
    {
        $show->delete();
    }
}
