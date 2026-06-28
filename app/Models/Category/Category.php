<?php

namespace App\Models\Category;

use App\Models\Category\Query\HasCategoryQuery;
use App\Models\Category\Relations\HasCategoryRelations;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'value', 'icon'])]
class Category extends Model
{
    use HasCategoryQuery;
    use HasCategoryRelations;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }
}
