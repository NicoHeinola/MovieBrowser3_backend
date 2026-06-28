<?php

namespace App\Models\Category\Relations;

use App\Models\Show\Show;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @mixin Model */
trait HasCategoryRelations
{
    public function shows(): BelongsToMany
    {
        return $this->belongsToMany(Show::class, 'category_show')->withTimestamps();
    }
}
