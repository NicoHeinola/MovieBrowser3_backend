<?php

namespace App\Actions\Category;

use App\Models\Category\Category;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteCategoryAction
{
    use AsAction;

    public function handle(Category $category): void
    {
        $category->delete();
    }
}
