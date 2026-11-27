<?php

namespace App\Models\ShowWatchProgress\Query;

use Illuminate\Database\Eloquent\Builder;

trait HasShowWatchProgressQuery
{
    /**
     * The progress that VLC can continue from: it has a position after the start.
     */
    public function scopeResumable(Builder $query): void
    {
        $query->where('position_seconds', '>', 0);
    }
}
