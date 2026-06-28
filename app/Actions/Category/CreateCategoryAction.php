<?php

namespace App\Actions\Category;

use App\Dtos\Category\CreateCategoryData;
use App\Models\Category\Category;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateCategoryAction
{
    use AsAction;

    public function handle(CreateCategoryData $data): Category
    {
        return Category::query()->create([
            'name' => $data->name,
            'value' => $data->value,
            'icon' => $data->icon,
        ]);
    }
}
