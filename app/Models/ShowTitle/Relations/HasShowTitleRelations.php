<?php

namespace App\Models\ShowTitle\Relations;

use App\Models\Show\Show;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasShowTitleRelations
{
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
}
