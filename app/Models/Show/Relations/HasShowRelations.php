<?php

namespace App\Models\Show\Relations;

use App\Models\ShowEntry\ShowEntry;
use App\Models\ShowLink\ShowLink;
use App\Models\ShowTitle\ShowTitle;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasShowRelations
{
    public function titles(): HasMany
    {
        return $this->hasMany(ShowTitle::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ShowEntry::class)->chaperone('show')->orderBy('sort_order');
    }

    /**
     * Links where this show is the source (i.e. outgoing links)
     */
    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(ShowLink::class, 'source_show_id');
    }

    /**
     * Links where this show is the target (i.e. incoming links)
     */
    public function incomingLinks(): HasMany
    {
        return $this->hasMany(ShowLink::class, 'target_show_id');
    }
}
