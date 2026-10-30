<?php

namespace App\Actions\Category;

use App\Dtos\Category\UpdateCategoryData;
use App\Models\Category\Category;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateCategoryAction
{
    use AsAction;

    public function handle(UpdateCategoryData $data): Category
    {
        return DB::transaction(function () use ($data): Category {
            $data->category->fill(Arr::only($data->all(), $data->category->getFillable()))->save();

            return $data->category->fresh();
        });
    }
}
