<?php

namespace App\Models\Episode\Relations;

use App\Models\ShowEntry\ShowEntry;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasEpisodeRelations
{
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ShowEntry::class, 'show_entry_id');
    }
}
