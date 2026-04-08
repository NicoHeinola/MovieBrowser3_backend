<?php

namespace App\Actions\ShowTitle;

use App\Models\Show\Show;
use App\Models\ShowTitle\ShowTitle;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowTitleAction
{
    use AsAction;

    /**
     * @param  array{title: string, is_primary: bool}  $attributes
     */
    public function handle(Show $show, array $attributes): ShowTitle
    {
        return $show->titles()->create($attributes);
    }
}
