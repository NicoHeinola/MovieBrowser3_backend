<?php

namespace App\Models\Show\Relations;

use App\Models\ShowTitle\ShowTitle;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasShowRelations
{
    public function titles(): HasMany
    {
        return $this->hasMany(ShowTitle::class)->orderByDesc('is_primary')->orderBy('id');
    }
}
