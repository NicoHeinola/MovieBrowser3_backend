<?php

namespace App\Actions\ShowTitle;

use App\Dtos\Show\ShowTitleData;
use App\Models\Show\Show;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncShowTitlesAction
{
    use AsAction;

    public function __construct(private readonly CreateShowTitleAction $createShowTitleAction) {}

    /**
     * @param  array<int, ShowTitleData>  $titles
     */
    public function handle(Show $show, array $titles): void
    {
        $show->titles()->delete();

        foreach ($titles as $title) {
            $this->createShowTitleAction->handle($show, $title->title, $title->isPrimary);
        }
    }
}
