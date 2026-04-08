<?php

namespace App\Actions\Show;

use App\Actions\ShowTitle\CreateShowTitleAction;
use App\Models\Show\Show;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateShowAction
{
    use AsAction;

    public function __construct(private readonly CreateShowTitleAction $createShowTitleAction) {}

    /**
     * @param  array<string, string|null>  $attributes
     * @param  array<int, array{title: string, is_primary: bool}>  $titles
     */
    public function handle(array $attributes, array $titles): Show
    {
        return DB::transaction(function () use ($attributes, $titles): Show {
            $show = Show::create($attributes);

            foreach ($titles as $title) {
                $this->createShowTitleAction->handle($show, $title);
            }

            return $show->load('titles');
        });
    }
}
