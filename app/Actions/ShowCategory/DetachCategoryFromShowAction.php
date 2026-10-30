<?php

namespace App\Actions\ShowCategory;

use App\Models\Category\Category;
use App\Models\Show\Show;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DetachCategoryFromShowAction
{
    use AsAction;

    public function handle(Show $show, Category $category): void
    {
        DB::transaction(function () use ($show, $category): void {
            $show->categories()->detach($category->id);
        });
    }
}
