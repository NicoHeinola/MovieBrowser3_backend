<?php

namespace App\Models\ShowSection\Relations;

use App\Models\Category\Category;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasShowSectionRelations
{
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
