<?php

namespace App\Models\ShowWatchProgress\Relations;

use App\Models\Episode\Episode;
use App\Models\Show\Show;
use App\Models\ShowEntry\ShowEntry;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasShowWatchProgressRelations
{
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ShowEntry::class, 'show_entry_id');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }
}
