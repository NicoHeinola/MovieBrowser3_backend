<?php

namespace App\Actions\Category;

use App\Models\Category\Category;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteCategoryAction
{
    use AsAction;

    public function handle(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $category->delete();
        });
    }
}
