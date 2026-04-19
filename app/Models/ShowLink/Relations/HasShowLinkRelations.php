<?php

namespace App\Models\ShowLink\Relations;

use App\Models\Show\Show;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasShowLinkRelations
{
    /**
     * Get the show that is the source of this link.
     */
    public function sourceShow(): BelongsTo
    {
        return $this->belongsTo(Show::class, 'source_show_id');
    }

    /**
     * Get the show that is the target of this link.
     */
    public function targetShow(): BelongsTo
    {
        return $this->belongsTo(Show::class, 'target_show_id');
    }
}
