<?php

namespace App\Actions\ShowCategory;

use App\Models\Category\Category;
use App\Models\Show\Show;
use Lorisleiva\Actions\Concerns\AsAction;

class AttachCategoryToShowAction
{
    use AsAction;

    public function handle(Show $show, Category $category): Category
    {
        $show->categories()->syncWithoutDetaching([$category->id]);

        return $category;
    }
}
