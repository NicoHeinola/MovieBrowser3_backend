<?php

namespace App\Actions\ShowTitle;

use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowTitleAction
{
    use AsAction;

    public function handle(Show $show, string $title, bool $isPrimary): ShowTitle
    {
        return $show->titles()->create([
            'title' => $title,
            'is_primary' => $isPrimary,
        ]);
    }
}
