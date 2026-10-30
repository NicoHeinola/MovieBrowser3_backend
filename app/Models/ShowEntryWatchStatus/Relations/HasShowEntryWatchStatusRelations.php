<?php

namespace App\Models\ShowEntryWatchStatus\Relations;

use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasShowEntryWatchStatusRelations
{
    public function showEntry(): BelongsTo
    {
        return $this->belongsTo(ShowEntry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
