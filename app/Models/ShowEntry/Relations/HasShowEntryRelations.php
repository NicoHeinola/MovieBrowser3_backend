<?php

namespace App\Models\ShowEntry\Relations;

use App\Models\Episode\Episode;
use App\Models\Show\Show;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasShowEntryRelations
{
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class)->chaperone('entry')->orderBy('sequence_number');
    }
}
