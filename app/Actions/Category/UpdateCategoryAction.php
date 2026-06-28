<?php

namespace App\Actions\Category;

use App\Dtos\Category\UpdateCategoryData;
use App\Models\Category\Category;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateCategoryAction
{
    use AsAction;

    public function handle(UpdateCategoryData $data): Category
    {
        $data->category->fill(Arr::only($data->all(), $data->category->getFillable()))->save();

        return $data->category->fresh();
    }
}
