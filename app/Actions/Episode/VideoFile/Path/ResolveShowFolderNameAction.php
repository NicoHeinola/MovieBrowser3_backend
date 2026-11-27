<?php

namespace App\Actions\Episode\VideoFile\Path;

use App\Models\Show\Show;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveShowFolderNameAction
{
    use AsAction;

    public function handle(Show $show): string
    {
        $show->loadMissing('titles');

        return $show->titles->firstWhere('is_primary', true)?->title ?? (string) $show->id;
    }
}
