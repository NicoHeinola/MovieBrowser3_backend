<?php

namespace App\Models\Category\Relations;

use App\Models\Show\Show;
use App\Models\ShowSection\ShowSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @mixin Model */
trait HasCategoryRelations
{
    public function shows(): BelongsToMany
    {
        return $this->belongsToMany(Show::class, 'category_show')->withTimestamps();
    }

    public function showSections(): HasMany
    {
        return $this->hasMany(ShowSection::class)->orderBy('sort_order')->orderBy('id');
    }
}
