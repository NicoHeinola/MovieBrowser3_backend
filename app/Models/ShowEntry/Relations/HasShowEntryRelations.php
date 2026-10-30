<?php

namespace App\Models\ShowEntry\Relations;

use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\ShowEntryWatchStatus\ShowEntryWatchStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasShowEntryRelations
{
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /**
     * Watch statuses of every user (at most one per user); constrain by user_id when loading for a specific user.
     */
    public function watchStatuses(): HasMany
    {
        return $this->hasMany(ShowEntryWatchStatus::class);
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class)->chaperone('entry')->orderBy('sequence_number');
    }
}
